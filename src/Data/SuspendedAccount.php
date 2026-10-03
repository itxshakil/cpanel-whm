<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

use Carbon\CarbonImmutable;

/**
 * One row of listsuspended.
 */
final readonly class SuspendedAccount
{
    /**
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public string $username,
        public ?string $owner,
        public ?string $reason,
        public ?CarbonImmutable $suspendedAt,
        public bool $locked,
        public array $raw = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            username: Value::string($row['user'] ?? null) ?? '',
            owner: Value::string($row['owner'] ?? null),
            reason: Value::string($row['reason'] ?? null),
            suspendedAt: Value::timestamp($row['unixtime'] ?? null),
            locked: Value::bool($row['is_locked'] ?? false),
            raw: $row,
        );
    }
}
