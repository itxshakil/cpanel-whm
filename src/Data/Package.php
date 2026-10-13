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
     * @param  bool|null  $shell  null when WHM didn't say
     * @param  bool|null  $cgi  null when WHM didn't say
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
        public ?string $maxAliases = null,
        public ?string $maxMailingLists = null,
        public ?string $maxEmailsPerHour = null,
        public ?string $maxEmailQuota = null,
        public ?string $theme = null,
        public ?string $language = null,
        public ?bool $shell = null,
        public ?bool $cgi = null,
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
            maxAliases: Value::string($row['MAXPARK'] ?? null),
            maxMailingLists: Value::string($row['MAXLST'] ?? null),
            maxEmailsPerHour: Value::string($row['MAX_EMAIL_PER_HOUR'] ?? null),
            maxEmailQuota: Value::string($row['MAX_EMAILACCT_QUOTA'] ?? null),
            theme: Value::string($row['CPMOD'] ?? null),
            language: Value::string($row['LANG'] ?? null),
            shell: isset($row['HASSHELL']) ? Value::bool($row['HASSHELL']) : null,
            cgi: isset($row['CGI']) ? Value::bool($row['CGI']) : null,
        );
    }

    /**
     * The same settings as a PackageDefinition, so a package can be saved
     * elsewhere without losing anything, e.g. copied to another server:
     *
     *     Whm::connection('ca-1')->packages()->create(Whm::packages()->info('starter')->toDefinition());
     */
    public function toDefinition(): PackageDefinition
    {
        return new PackageDefinition(
            name: $this->name,
            diskQuotaMb: $this->diskQuota,
            bandwidthMb: $this->bandwidthLimit,
            maxAddonDomains: $this->maxAddonDomains,
            maxAliases: $this->maxAliases,
            maxSubdomains: $this->maxSubdomains,
            maxEmailAccounts: $this->maxEmailAccounts,
            maxEmailQuotaMb: $this->maxEmailQuota,
            maxDatabases: $this->maxDatabases,
            maxFtpAccounts: $this->maxFtpAccounts,
            maxMailingLists: $this->maxMailingLists,
            maxEmailsPerHour: $this->maxEmailsPerHour,
            featureList: $this->featureList,
            theme: $this->theme,
            language: $this->language,
            shell: $this->shell,
            cgi: $this->cgi,
            dedicatedIp: $this->dedicatedIp,
        );
    }
}
