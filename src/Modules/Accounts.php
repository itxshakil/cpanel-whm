<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use Illuminate\Support\Collection;
use Itxshakil\CpanelWhm\Data\Account;
use Itxshakil\CpanelWhm\Data\CreatedAccount;
use Itxshakil\CpanelWhm\Data\NewAccount;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Enums\SearchType;
use Itxshakil\CpanelWhm\Events\AccountCreated;
use Itxshakil\CpanelWhm\Events\AccountModified;
use Itxshakil\CpanelWhm\Events\AccountPackageChanged;
use Itxshakil\CpanelWhm\Events\AccountPasswordChanged;
use Itxshakil\CpanelWhm\Events\AccountRemoved;
use Itxshakil\CpanelWhm\Exceptions\AccountNotFound;
use Itxshakil\CpanelWhm\Exceptions\InvalidUsername;
use Itxshakil\CpanelWhm\Exceptions\WhmCommandFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmConnectionFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Exceptions\WhmPermissionDenied;
use Itxshakil\CpanelWhm\Support\Filter;
use Itxshakil\CpanelWhm\Support\Passwords;
use Itxshakil\CpanelWhm\Support\Redactor;
use Itxshakil\CpanelWhm\Support\UsernameRules;
use Itxshakil\CpanelWhm\WhmResponse;
use SensitiveParameter;

/**
 * cPanel accounts: createacct, listaccts, accountsummary, removeacct, passwd,
 * changepackage, modifyacct, verify_new_username.
 */
class Accounts extends Module
{
    /**
     * createacct sets up DNS, mail, DKIM and the home directory, and can take
     * well over the default timeout on a busy server.
     */
    public const int CREATE_TIMEOUT = 120;

    /**
     * Create an account. The username is checked against cPanel's rules first.
     * Without a password, a strong one is generated; CreatedAccount::$password
     * holds the one the account was created with.
     *
     * @throws InvalidUsername
     * @throws WhmException
     */
    public function create(NewAccount $account): CreatedAccount
    {
        // WHM does not return a password it generates, so one is generated here
        // and handed back on CreatedAccount.
        $password = $account->givenPassword() ?? Passwords::generate();
        $params = $account->withPassword($password)->toParams();

        $response = $this->client->call('createacct', $params, HttpMethod::Post, max(self::CREATE_TIMEOUT, $this->client->config()->timeout));

        $username = $params['username'] ?? '';
        $domain = $params['domain'] ?? null;

        $created = CreatedAccount::fromResponse($response, is_string($username) ? $username : '', is_string($domain) ? $domain : null, $password);

        $this->client->dispatch(new AccountCreated($this->client->config()->name, $created->withoutPassword()));

        return $created;
    }

    /**
     * create(), made safe to call from a job that may time out. createacct can
     * take minutes; when the connection drops after the request was sent
     * (WhmConnectionFailed::mayHaveReachedServer()), WHM may have created the
     * account anyway. In that case the account is looked up: if it exists with
     * the requested domain it is returned with $recovered = true and the
     * password the request carried, and AccountCreated fires as usual.
     *
     * The original WhmConnectionFailed is rethrown when nothing could have
     * reached WHM, when no account was found (a retry is safe), or when the
     * name belongs to an account with another domain.
     *
     * @throws InvalidUsername
     * @throws WhmException
     */
    public function createOrRecover(NewAccount $account): CreatedAccount
    {
        // The password must be known before sending, so a recovered account can hand it back.
        $password = $account->givenPassword() ?? Passwords::generate();
        $account = $account->withPassword($password);

        try {
            return $this->create($account);
        } catch (WhmConnectionFailed $whmConnectionFailed) {
            if (! $whmConnectionFailed->mayHaveReachedServer()) {
                throw $whmConnectionFailed;
            }

            $params = $account->toParams();
            $username = is_string($params['username'] ?? null) ? $params['username'] : '';
            $domain = is_string($params['domain'] ?? null) ? $params['domain'] : null;

            // Uncached: a cached "no such account" from before the call would hide the new one.
            $existing = $this->client->withoutCache()->accounts()->find($username);

            if (! $existing instanceof Account || ($domain !== null && mb_strtolower($existing->domain) !== $domain)) {
                throw $whmConnectionFailed;
            }

            $created = CreatedAccount::recovered($existing, $password);

            $this->client->dispatch(new AccountCreated($this->client->config()->name, $created->withoutPassword()));

            return $created;
        }
    }

    /**
     * Every account, optionally narrowed with a WHM output filter.
     *
     * @return Collection<int, Account>
     *
     * @throws WhmException
     */
    public function list(?Filter $filter = null): Collection
    {
        return $this->listWith($filter?->toParams() ?? []);
    }

    /**
     * listaccts' own search: by user, domain, owner, IP or package.
     *
     * @return Collection<int, Account>
     *
     * @throws WhmException
     */
    public function search(string $term, SearchType $by = SearchType::User, bool $exact = false): Collection
    {
        return $this->listWith([
            'searchtype' => $by->value,
            'search' => $exact ? $term : preg_quote($term, null),
            'searchmethod' => $exact ? 'exact' : 'regex',
        ]);
    }

    /**
     * One account's summary, or null when it does not exist.
     *
     * @throws WhmException
     */
    public function find(string $user): ?Account
    {
        try {
            $response = $this->client->call('accountsummary', ['user' => UsernameRules::normalise($user)]);
        } catch (AccountNotFound) {
            return null;
        } catch (WhmCommandFailed $whmCommandFailed) {
            // accountsummary is about one account, so any "does not exist" means that one.
            if ($this->isMissingAccount($whmCommandFailed->reason())) {
                return null;
            }

            throw $whmCommandFailed;
        }

        $rows = $this->rows($response->get('acct'));

        return $rows === [] ? null : Account::fromArray($rows[0]);
    }

    /**
     * @throws WhmException
     */
    public function exists(string $user): bool
    {
        return $this->find($user) instanceof Account;
    }

    /**
     * Whether WHM would accept this username for a new account. Local rules are
     * checked first, so an obviously bad name costs no request.
     *
     * @throws WhmException when WHM cannot be reached
     */
    public function isUsernameAvailable(string $username): bool
    {
        if (! UsernameRules::passes($username)) {
            return false;
        }

        try {
            return $this->client->call('verify_new_username', ['user' => UsernameRules::normalise($username)])->successful();
        } catch (WhmPermissionDenied $whmPermissionDenied) {
            // A token without the privilege must not read as "name taken".
            throw $whmPermissionDenied;
        } catch (WhmCommandFailed) {
            return false;
        }
    }

    /**
     * Delete an account and its data. This cannot be undone.
     *
     * @throws WhmException
     */
    public function remove(string $user, bool $keepDns = false): WhmResponse
    {
        $user = UsernameRules::normalise($user);

        $response = $this->client->call('removeacct', [
            'user' => $user,
            'keepdns' => $keepDns ? 1 : 0,
        ], HttpMethod::Post);

        $this->client->dispatch(new AccountRemoved($this->client->config()->name, $user));

        return $response;
    }

    /**
     * Change the account's password. Sent as a POST body, never in the URL.
     *
     * @throws WhmException
     */
    public function changePassword(string $user, #[SensitiveParameter] string $password, bool $syncDatabasePasswords = false): WhmResponse
    {
        $user = UsernameRules::normalise($user);

        $response = $this->client->call('passwd', [
            'user' => $user,
            'password' => $password,
            'db_pass_update' => $syncDatabasePasswords ? 1 : 0,
        ], HttpMethod::Post);

        $this->client->dispatch(new AccountPasswordChanged($this->client->config()->name, $user));

        return $response;
    }

    /**
     * Move the account to another hosting package.
     *
     * @throws WhmException
     */
    public function changePackage(string $user, string $package): WhmResponse
    {
        $user = UsernameRules::normalise($user);

        $response = $this->client->call('changepackage', [
            'user' => $user,
            'pkg' => $package,
        ], HttpMethod::Post);

        $this->client->dispatch(new AccountPackageChanged($this->client->config()->name, $user, $package));

        return $response;
    }

    /**
     * Change account settings with modifyacct, e.g. ['CONTACTEMAIL' => '...', 'BACKUP' => 1].
     *
     * @param  array<string, mixed>  $changes
     *
     * @throws WhmException
     */
    public function modify(string $user, array $changes): WhmResponse
    {
        unset($changes['user']);
        $user = UsernameRules::normalise($user);

        $response = $this->client->call('modifyacct', ['user' => $user, ...$changes], HttpMethod::Post);

        $this->client->dispatch(new AccountModified($this->client->config()->name, $user, Redactor::redact($changes)));

        return $response;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return Collection<int, Account>
     *
     * @throws WhmException
     */
    private function listWith(array $params): Collection
    {
        $response = $this->client->call('listaccts', $params);

        return collect($this->rows($response->get('acct')))->map(Account::fromArray(...))->values();
    }

    private function isMissingAccount(string $reason): bool
    {
        $reason = mb_strtolower($reason);

        return str_contains($reason, 'does not exist') || str_contains($reason, 'no such user');
    }
}
