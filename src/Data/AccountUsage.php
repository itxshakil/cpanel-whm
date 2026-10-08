<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

/**
 * Disk and bandwidth for one account, from Whm::usage()->account().
 */
final readonly class AccountUsage
{
    public function __construct(
        public string $user,
        public ?DiskUsage $disk,
        public ?BandwidthUsage $bandwidth,
    ) {}

    /**
     * The higher of the disk and bandwidth shares, 0-100, or null when neither has a limit.
     */
    public function highestPercent(): ?float
    {
        $percents = array_filter([$this->disk?->percentUsed(), $this->bandwidth?->percentUsed()], is_float(...));

        return $percents === [] ? null : max($percents);
    }

    /**
     * The account's main domain, when showbw reported it.
     */
    public function domain(): ?string
    {
        return $this->bandwidth?->domain;
    }

    /**
     * Whether disk or bandwidth use is at or above $percent of its limit.
     */
    public function isNearLimit(float $percent = 90.0): bool
    {
        return ($this->disk?->percentUsed() ?? 0) >= $percent
            || ($this->bandwidth?->percentUsed() ?? 0) >= $percent;
    }
}
