<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

/**
 * One mounted partition on the server, from getdiskusage. Sizes are in bytes.
 */
final readonly class DiskPartition
{
    public function __construct(
        public string $mount,
        public string $device,
        public int $totalBytes,
        public int $usedBytes,
        public int $availableBytes,
        public int $percentUsed,
        public ?int $inodesPercentUsed,
    ) {}

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        $kb = static fn (mixed $value): int => (Value::int($value) ?? 0) * 1024;

        return new self(
            mount: Value::string($row['mount'] ?? null) ?? Value::string($row['filesystem'] ?? null) ?? '',
            device: Value::string($row['device'] ?? null) ?? '',
            totalBytes: $kb($row['total'] ?? null),
            usedBytes: $kb($row['used'] ?? null),
            availableBytes: $kb($row['available'] ?? null),
            percentUsed: Value::int($row['percentage'] ?? null) ?? 0,
            inodesPercentUsed: Value::int($row['inodes_ipercentage'] ?? null),
        );
    }
}
