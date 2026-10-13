<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

/**
 * A subdomain of one account, from UAPI SubDomain::listsubdomains.
 */
final readonly class Subdomain
{
    /**
     * @param  string  $domain  the full name, e.g. shop.example.com
     * @param  string  $label  the part before the root domain, e.g. shop
     * @param  string  $documentRoot  the absolute path, e.g. /home/acme/public_html/shop
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public string $domain,
        public string $label,
        public string $rootDomain,
        public string $documentRoot,
        public array $raw,
    ) {}

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            domain: Value::string($row['domain'] ?? null) ?? '',
            label: Value::string($row['subdomain'] ?? null) ?? '',
            rootDomain: Value::string($row['rootdomain'] ?? null) ?? '',
            documentRoot: Value::string($row['dir'] ?? null) ?? '',
            raw: $row,
        );
    }
}
