<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

/**
 * How many cPanel accounts the server has, and how many its licence allows,
 * from get_current_users_count and get_maximum_users.
 */
final readonly class AccountLimits
{
    /**
     * @param  int|null  $maximum  null when the licence has no limit
     */
    public function __construct(
        public int $current,
        public ?int $maximum,
    ) {}

    /**
     * Accounts that can still be created, or null when there is no limit.
     */
    public function remaining(): ?int
    {
        return $this->maximum === null ? null : max(0, $this->maximum - $this->current);
    }

    public function isFull(): bool
    {
        return $this->remaining() === 0;
    }
}
