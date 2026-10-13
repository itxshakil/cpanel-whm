<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use Illuminate\Support\Collection;
use Itxshakil\CpanelWhm\Data\Account;
use Itxshakil\CpanelWhm\Data\SuspendedAccount;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Events\AccountSuspended;
use Itxshakil\CpanelWhm\Events\AccountUnsuspended;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Support\UsernameRules;
use Itxshakil\CpanelWhm\WhmResponse;

/**
 * suspendacct, unsuspendacct, listsuspended.
 */
class Suspensions extends Module
{
    /**
     * @param  bool  $lock  stop the account's reseller from unsuspending it
     *
     * @throws WhmException
     */
    public function suspend(string $user, string $reason = '', bool $lock = false): WhmResponse
    {
        $user = UsernameRules::normalise($user);
        $params = ['user' => $user, 'reason' => $reason];

        if ($lock) {
            $params['disallowun'] = 1;
        }

        $response = $this->client->call('suspendacct', $params, HttpMethod::Post);

        $this->client->dispatch(new AccountSuspended($this->client->config()->name, $user, $reason, $lock));

        return $response;
    }

    /**
     * @throws WhmException
     */
    public function unsuspend(string $user): WhmResponse
    {
        $user = UsernameRules::normalise($user);

        $response = $this->client->call('unsuspendacct', ['user' => $user], HttpMethod::Post);

        $this->client->dispatch(new AccountUnsuspended($this->client->config()->name, $user));

        return $response;
    }

    /**
     * Suspend the account unless it already is. Safe to re-run from a retried
     * job. Returns true when this call suspended it.
     *
     * The state is read first rather than parsed out of WHM's error text, which
     * varies between versions. A missing account still throws.
     *
     * @throws WhmException
     */
    public function ensureSuspended(string $user, string $reason = '', bool $lock = false): bool
    {
        if ($this->currentAccount($user)?->suspended === true) {
            return false;
        }

        $this->suspend($user, $reason, $lock);

        return true;
    }

    /**
     * Unsuspend the account unless it is already active. Returns true when this
     * call unsuspended it. A missing account still throws.
     *
     * @throws WhmException
     */
    public function ensureUnsuspended(string $user): bool
    {
        if ($this->currentAccount($user)?->suspended === false) {
            return false;
        }

        $this->unsuspend($user);

        return true;
    }

    /**
     * @return Collection<int, SuspendedAccount>
     *
     * @throws WhmException
     */
    public function list(): Collection
    {
        $response = $this->client->call('listsuspended');

        return collect($this->rows($response->get('account')))->map(SuspendedAccount::fromArray(...))->values();
    }

    /**
     * Read uncached: a cached summary would say the state from before the change.
     *
     * @throws WhmException
     */
    private function currentAccount(string $user): ?Account
    {
        return (new Accounts($this->client->withoutCache()))->find($user);
    }
}
