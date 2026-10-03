<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

/**
 * A reseller's totals for a month, from resellerstats. Disk and bandwidth
 * figures are in megabytes, as WHM reports them; 0 means unlimited.
 */
final readonly class ResellerStats
{
    /**
     * @param  list<array<array-key, mixed>>  $accounts  one row per owned account (user, domain, package, diskused, ...)
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public string $user,
        public float $diskUsedMb,
        public int $diskQuotaMb,
        public float $diskAllocatedMb,
        public float $bandwidthUsedMb,
        public int $bandwidthLimitMb,
        public float $bandwidthAllocatedMb,
        public bool $diskOverselling,
        public bool $bandwidthOverselling,
        public array $accounts,
        public array $raw = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $float = static fn (mixed $value): float => is_numeric($value) ? (float) $value : 0.0;
        $accounts = array_values(array_filter(is_array($data['acct'] ?? null) ? $data['acct'] : [], is_array(...)));

        return new self(
            user: Value::string($data['user'] ?? null) ?? '',
            diskUsedMb: $float($data['diskused'] ?? null),
            diskQuotaMb: Value::int($data['diskquota'] ?? null) ?? 0,
            diskAllocatedMb: $float($data['totaldiskalloc'] ?? null),
            bandwidthUsedMb: $float($data['totalbwused'] ?? null),
            bandwidthLimitMb: Value::int($data['bandwidthlimit'] ?? null) ?? 0,
            bandwidthAllocatedMb: $float($data['totalbwalloc'] ?? null),
            diskOverselling: Value::bool($data['diskoverselling'] ?? false),
            bandwidthOverselling: Value::bool($data['bwoverselling'] ?? false),
            accounts: $accounts,
            raw: $data,
        );
    }
}
