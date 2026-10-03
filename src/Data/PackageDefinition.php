<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

/**
 * The settings for addpkg and editpkg. Sizes are in megabytes. Each limit
 * takes a number, PackageDefinition::UNLIMITED, or null to leave WHM's
 * default (on create) or the current value (on update).
 *
 *     Whm::packages()->create(new PackageDefinition(
 *         name: 'starter',
 *         diskQuotaMb: 10_240,
 *         bandwidthMb: PackageDefinition::UNLIMITED,
 *         maxEmailAccounts: 10,
 *     ));
 */
final readonly class PackageDefinition
{
    public const string UNLIMITED = 'unlimited';

    /**
     * @param  array<string, mixed>  $extra  any other addpkg/editpkg parameter
     */
    public function __construct(
        public string $name,
        public int|string|null $diskQuotaMb = null,
        public int|string|null $bandwidthMb = null,
        public int|string|null $maxAddonDomains = null,
        public int|string|null $maxAliases = null,
        public int|string|null $maxSubdomains = null,
        public int|string|null $maxEmailAccounts = null,
        public int|string|null $maxEmailQuotaMb = null,
        public int|string|null $maxDatabases = null,
        public int|string|null $maxFtpAccounts = null,
        public int|string|null $maxMailingLists = null,
        public int|string|null $maxEmailsPerHour = null,
        public ?string $featureList = null,
        public ?string $theme = null,
        public ?string $language = null,
        public ?bool $shell = null,
        public ?bool $cgi = null,
        public ?bool $dedicatedIp = null,
        public array $extra = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toParams(bool $forUpdate = false): array
    {
        return [
            'name' => $this->name,
            'quota' => $this->diskQuotaMb,
            'bwlimit' => $this->bandwidthMb,
            'maxaddon' => $this->maxAddonDomains,
            'maxpark' => $this->maxAliases,
            'maxsub' => $this->maxSubdomains,
            'maxpop' => $this->maxEmailAccounts,
            'max_emailacct_quota' => $this->maxEmailQuotaMb,
            'maxsql' => $this->maxDatabases,
            'maxftp' => $this->maxFtpAccounts,
            'maxlst' => $this->maxMailingLists,
            // addpkg spells this in capitals, editpkg in lower case.
            $forUpdate ? 'max_email_per_hour' : 'MAX_EMAIL_PER_HOUR' => $this->maxEmailsPerHour,
            'featurelist' => $this->featureList,
            'cpmod' => $this->theme,
            'language' => $this->language,
            'hasshell' => $this->shell,
            'cgi' => $this->cgi,
            'ip' => $this->dedicatedIp === null ? null : ($this->dedicatedIp ? 'y' : 'n'),
            ...$this->extra,
        ];
    }
}
