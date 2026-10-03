<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Support\UsernameRules;
use Itxshakil\CpanelWhm\WhmResponse;

/**
 * Disk and bandwidth limits: editquota, limitbw.
 */
class Quotas extends Module
{
    /**
     * @param  int|null  $megabytes  null for unlimited
     *
     * @throws WhmException
     */
    public function setDiskQuota(string $user, ?int $megabytes): WhmResponse
    {
        return $this->client->call('editquota', [
            'user' => UsernameRules::normalise($user),
            'quota' => $megabytes ?? 0,
        ]);
    }

    /**
     * @param  int|null  $megabytes  null for unlimited
     *
     * @throws WhmException
     */
    public function setBandwidthLimit(string $user, ?int $megabytes): WhmResponse
    {
        return $this->client->call('limitbw', [
            'user' => UsernameRules::normalise($user),
            'bwlimit' => $megabytes ?? 'unlimited',
        ]);
    }
}
