<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

/**
 * An account's disk and inode use, from get_disk_usage. Sizes are in bytes;
 * a null limit means unlimited.
 */
final readonly class DiskUsage
{
    public function __construct(
        public string $user,
        public int $usedBytes,
        public ?int $limitBytes,
        public int $inodesUsed,
        public ?int $inodesLimit,
    ) {}

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        $limit = Value::int($row['blocks_limit'] ?? null);
        $inodesLimit = Value::int($row['inodes_limit'] ?? null);

        return new self(
            user: Value::string($row['user'] ?? null) ?? '',
            usedBytes: (Value::int($row['blocks_used'] ?? null) ?? 0) * 1024,
            limitBytes: $limit !== null && $limit > 0 ? $limit * 1024 : null,
            inodesUsed: Value::int($row['inodes_used'] ?? null) ?? 0,
            inodesLimit: $inodesLimit !== null && $inodesLimit > 0 ? $inodesLimit : null,
        );
    }

    public function isUnlimited(): bool
    {
        return $this->limitBytes === null;
    }

    /**
     * Share of the quota in use, 0-100, or null when unlimited.
     */
    public function percentUsed(): ?float
    {
        return $this->limitBytes === null ? null : round($this->usedBytes / $this->limitBytes * 100, 1);
    }

    public function usedMegabytes(): float
    {
        return round($this->usedBytes / 1_048_576, 2);
    }
}
