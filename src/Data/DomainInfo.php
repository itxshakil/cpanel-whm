<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

/**
 * One domain on the server, from get_domain_info.
 */
final readonly class DomainInfo
{
    /**
     * @param  string  $type  main, addon, sub or parked (an alias)
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public string $domain,
        public string $user,
        public ?string $owner,
        public string $type,
        public ?string $parentDomain,
        public ?string $documentRoot,
        public ?string $ipv4,
        public ?string $ipv6,
        public ?string $phpVersion,
        public array $raw = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            domain: Value::string($row['domain'] ?? null) ?? '',
            user: Value::string($row['user'] ?? null) ?? '',
            owner: Value::string($row['user_owner'] ?? null),
            type: Value::string($row['domain_type'] ?? null) ?? 'main',
            parentDomain: Value::string($row['parent_domain'] ?? null),
            documentRoot: Value::string($row['docroot'] ?? null),
            ipv4: Value::string($row['ipv4'] ?? null),
            ipv6: Value::string($row['ipv6'] ?? null),
            phpVersion: Value::string($row['php_version'] ?? null),
            raw: $row,
        );
    }

    public function isMain(): bool
    {
        return $this->type === 'main';
    }

    public function isAddon(): bool
    {
        return $this->type === 'addon';
    }

    public function isSubdomain(): bool
    {
        return $this->type === 'sub';
    }

    public function isAlias(): bool
    {
        return $this->type === 'parked';
    }
}
