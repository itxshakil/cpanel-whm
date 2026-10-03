<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

/**
 * A hosting package (plan) as listpkgs describes it. Limits are kept as WHM
 * sends them: a number of megabytes or items, or "unlimited".
 */
final readonly class Package
{
    /**
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public string $name,
        public ?string $diskQuota,
        public ?string $bandwidthLimit,
        public ?string $featureList,
        public ?string $maxAddonDomains,
        public ?string $maxSubdomains,
        public ?string $maxEmailAccounts,
        public ?string $maxDatabases,
        public ?string $maxFtpAccounts,
        public bool $dedicatedIp,
        public array $raw = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            name: Value::string($row['name'] ?? null) ?? '',
            diskQuota: Value::string($row['QUOTA'] ?? null),
            bandwidthLimit: Value::string($row['BWLIMIT'] ?? null),
            featureList: Value::string($row['FEATURELIST'] ?? null),
            maxAddonDomains: Value::string($row['MAXADDON'] ?? null),
            maxSubdomains: Value::string($row['MAXSUB'] ?? null),
            maxEmailAccounts: Value::string($row['MAXPOP'] ?? null),
            maxDatabases: Value::string($row['MAXSQL'] ?? null),
            maxFtpAccounts: Value::string($row['MAXFTP'] ?? null),
            dedicatedIp: Value::bool($row['IP'] ?? false),
            raw: $row,
        );
    }
}
