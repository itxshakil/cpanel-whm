<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use Illuminate\Support\Collection;
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
     * @return Collection<int, SuspendedAccount>
     *
     * @throws WhmException
     */
    public function list(): Collection
    {
        $response = $this->client->call('listsuspended');

        return collect($this->rows($response->get('account')))->map(SuspendedAccount::fromArray(...))->values();
    }
}
