<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

use Carbon\CarbonImmutable;

/**
 * A cPanel account as listaccts and accountsummary describe it.
 */
final readonly class Account
{
    /**
     * @param  array<array-key, mixed>  $raw  everything WHM returned for the account
     */
    public function __construct(
        public string $username,
        public string $domain,
        public ?string $package,
        public ?string $ip,
        public ?string $email,
        public ?string $owner,
        public bool $suspended,
        public ?string $suspendReason,
        public ?string $diskUsed,
        public ?string $diskLimit,
        public ?CarbonImmutable $createdAt,
        public array $raw = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        $suspended = Value::bool($row['suspended'] ?? false);
        $email = Value::string($row['email'] ?? null);

        return new self(
            username: Value::string($row['user'] ?? null) ?? '',
            domain: Value::string($row['domain'] ?? null) ?? '',
            package: Value::string($row['plan'] ?? null),
            ip: Value::string($row['ip'] ?? null),
            email: $email === '*unknown*' ? null : $email,
            owner: Value::string($row['owner'] ?? null),
            suspended: $suspended,
            suspendReason: $suspended ? Value::string($row['suspendreason'] ?? null) : null,
            diskUsed: Value::string($row['diskused'] ?? null),
            diskLimit: Value::string($row['disklimit'] ?? null),
            createdAt: Value::timestamp($row['unix_startdate'] ?? null),
            raw: $row,
        );
    }

    public function isSuspended(): bool
    {
        return $this->suspended;
    }
}
