<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

/**
 * An account's bandwidth for one month, from showbw. Sizes are in bytes; a
 * null limit means unlimited.
 */
final readonly class BandwidthUsage
{
    /**
     * @param  array<string, int>  $byDomain  bytes per domain
     */
    public function __construct(
        public string $user,
        public ?string $domain,
        public ?string $owner,
        public int $usedBytes,
        public ?int $limitBytes,
        public array $byDomain,
        public int $month,
        public int $year,
    ) {}

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function fromArray(array $row, int $month, int $year): self
    {
        $byDomain = [];

        foreach (is_array($row['bwusage'] ?? null) ? $row['bwusage'] : [] as $usage) {
            if (is_array($usage) && is_string($usage['domain'] ?? null)) {
                $byDomain[$usage['domain']] = Value::int($usage['usage'] ?? null) ?? 0;
            }
        }

        $limit = Value::int($row['limit'] ?? null);

        return new self(
            user: Value::string($row['user'] ?? null) ?? '',
            domain: Value::string($row['maindomain'] ?? null),
            owner: Value::string($row['owner'] ?? null),
            usedBytes: Value::int($row['totalbytes'] ?? null) ?? 0,
            limitBytes: $limit !== null && $limit > 0 ? $limit : null,
            byDomain: $byDomain,
            month: $month,
            year: $year,
        );
    }

    public function isUnlimited(): bool
    {
        return $this->limitBytes === null;
    }

    public function percentUsed(): ?float
    {
        return $this->limitBytes === null ? null : round($this->usedBytes / $this->limitBytes * 100, 1);
    }

    public function usedMegabytes(): float
    {
        return round($this->usedBytes / 1_048_576, 2);
    }
}
