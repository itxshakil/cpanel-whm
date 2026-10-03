<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use Illuminate\Support\Collection;
use Itxshakil\CpanelWhm\Data\SuspendedAccount;
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
        $params = ['user' => UsernameRules::normalise($user), 'reason' => $reason];

        if ($lock) {
            $params['disallowun'] = 1;
        }

        return $this->client->call('suspendacct', $params);
    }

    /**
     * @throws WhmException
     */
    public function unsuspend(string $user): WhmResponse
    {
        return $this->client->call('unsuspendacct', ['user' => UsernameRules::normalise($user)]);
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
