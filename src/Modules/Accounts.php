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
use Itxshakil\CpanelWhm\Events\AccountPackageChanged;
use Itxshakil\CpanelWhm\Events\AccountPasswordChanged;
use Itxshakil\CpanelWhm\Events\AccountRemoved;
use Itxshakil\CpanelWhm\Exceptions\InvalidUsername;
use Itxshakil\CpanelWhm\Exceptions\WhmCommandFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Support\Filter;
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
     *
     * @throws InvalidUsername
     * @throws WhmException
     */
    public function create(NewAccount $account): CreatedAccount
    {
        $params = $account->toParams();

        $response = $this->client->call('createacct', $params, HttpMethod::Post, max(self::CREATE_TIMEOUT, $this->client->config()->timeout));

        $username = $params['username'] ?? '';
        $domain = $params['domain'] ?? null;

        $created = CreatedAccount::fromResponse($response, is_string($username) ? $username : '', is_string($domain) ? $domain : null);

        $this->client->dispatch(new AccountCreated($this->client->config()->name, $created));

        return $created;
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
        } catch (WhmCommandFailed $whmCommandFailed) {
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
        return $this->client->call('modifyacct', ['user' => UsernameRules::normalise($user), ...$changes], HttpMethod::Post);
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
