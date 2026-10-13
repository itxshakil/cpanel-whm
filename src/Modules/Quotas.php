<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use InvalidArgumentException;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Support\UsernameRules;
use Itxshakil\CpanelWhm\WhmResponse;

/**
 * Disk and bandwidth limits: editquota, limitbw.
 */
class Quotas extends Module
{
    /**
     * @param  int|null  $megabytes  null for unlimited; at least 1 otherwise
     *
     * @throws InvalidArgumentException when $megabytes is 0 or less
     * @throws WhmException
     */
    public function setDiskQuota(string $user, ?int $megabytes): WhmResponse
    {
        return $this->client->call('editquota', [
            'user' => UsernameRules::normalise($user),
            'quota' => self::megabytes($megabytes) ?? 0,
        ]);
    }

    /**
     * @param  int|null  $megabytes  null for unlimited; at least 1 otherwise
     *
     * @throws InvalidArgumentException when $megabytes is 0 or less
     * @throws WhmException
     */
    public function setBandwidthLimit(string $user, ?int $megabytes): WhmResponse
    {
        return $this->client->call('limitbw', [
            'user' => UsernameRules::normalise($user),
            'bwlimit' => self::megabytes($megabytes) ?? 'unlimited',
        ]);
    }

    /**
     * WHM reads 0 as unlimited, so a 0 meant as "nothing" would grant the
     * opposite. Only null may mean unlimited.
     */
    private static function megabytes(?int $megabytes): ?int
    {
        if ($megabytes !== null && $megabytes < 1) {
            throw new InvalidArgumentException("A limit of {$megabytes} MB isn't possible: WHM reads 0 as unlimited. Pass null for unlimited, or at least 1.");
        }

        return $megabytes;
    }
}
