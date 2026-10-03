<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * A WHM API token's details, from api_token_list. The token value itself is
 * only ever returned once, by Whm::tokens()->create().
 */
final readonly class ApiToken
{
    /**
     * @param  list<string>  $privileges  the token's enabled ACLs; ['all'] for full access
     * @param  list<string>  $allowedIps  IPs or CIDR ranges allowed to use it; empty means any
     */
    public function __construct(
        public string $name,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $expiresAt,
        public array $privileges,
        public array $allowedIps,
    ) {}

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function fromArray(string $name, array $row): self
    {
        $privileges = [];

        foreach (is_array($row['acls'] ?? null) ? $row['acls'] : [] as $acl => $enabled) {
            if (is_int($acl) && is_string($enabled)) {
                $privileges[] = $enabled;    // some versions return a plain list
            } elseif (Value::bool($enabled)) {
                $privileges[] = (string) $acl;
            }
        }

        return new self(
            name: Value::string($row['name'] ?? null) ?? $name,
            createdAt: Value::timestamp($row['create_time'] ?? null),
            expiresAt: Value::timestamp($row['expires_at'] ?? null),
            privileges: $privileges,
            allowedIps: Value::strings($row['whitelist_ips'] ?? null),
        );
    }

    public function expires(): bool
    {
        return $this->expiresAt instanceof CarbonImmutable;
    }

    public function isExpired(?DateTimeInterface $now = null): bool
    {
        return $this->expiresAt instanceof CarbonImmutable && $this->expiresAt->lessThanOrEqualTo($now ?? CarbonImmutable::now());
    }

    /**
     * Whether the token expires within $days (or has already expired).
     */
    public function expiresWithin(int $days, ?DateTimeInterface $now = null): bool
    {
        $now = CarbonImmutable::instance($now ?? CarbonImmutable::now());

        return $this->expiresAt instanceof CarbonImmutable && $this->expiresAt->lessThanOrEqualTo($now->addDays($days));
    }

    public function daysLeft(?DateTimeInterface $now = null): ?int
    {
        if (! $this->expiresAt instanceof CarbonImmutable) {
            return null;
        }

        return (int) floor(CarbonImmutable::instance($now ?? CarbonImmutable::now())->diffInDays($this->expiresAt, false));
    }
}
