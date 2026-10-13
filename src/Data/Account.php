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
     * Limits are kept as WHM sends them: a number or "unlimited".
     *
     * @param  array<array-key, mixed>  $raw  everything WHM returned for the account
     * @param  list<string>  $ipv6
     * @param  bool  $locked  suspended so that only root can unsuspend it
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
        public ?CarbonImmutable $suspendedAt = null,
        public bool $locked = false,
        public array $ipv6 = [],
        public bool $backupEnabled = false,
        public ?string $shell = null,
        public ?string $theme = null,
        public ?string $partition = null,
        public ?string $inodesUsed = null,
        public ?string $inodesLimit = null,
        public ?string $maxEmailsPerHour = null,
        public ?string $maxEmailAccounts = null,
        public ?string $maxDatabases = null,
        public ?string $maxAddonDomains = null,
        public ?string $maxSubdomains = null,
        public ?string $maxAliases = null,
        public ?string $maxFtpAccounts = null,
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
            suspendedAt: $suspended ? Value::timestamp($row['suspendtime'] ?? null) : null,
            locked: Value::bool($row['is_locked'] ?? false),
            ipv6: Value::strings($row['ipv6'] ?? null),
            backupEnabled: Value::bool($row['backup'] ?? false),
            shell: Value::string($row['shell'] ?? null),
            theme: Value::string($row['theme'] ?? null),
            partition: Value::string($row['partition'] ?? null),
            inodesUsed: Value::string($row['inodesused'] ?? null),
            inodesLimit: Value::string($row['inodeslimit'] ?? null),
            maxEmailsPerHour: Value::string($row['max_email_per_hour'] ?? null),
            maxEmailAccounts: Value::string($row['maxpop'] ?? null),
            maxDatabases: Value::string($row['maxsql'] ?? null),
            maxAddonDomains: Value::string($row['maxaddons'] ?? null),
            maxSubdomains: Value::string($row['maxsub'] ?? null),
            maxAliases: Value::string($row['maxparked'] ?? null),
            maxFtpAccounts: Value::string($row['maxftp'] ?? null),
        );
    }

    public function isSuspended(): bool
    {
        return $this->suspended;
    }
}
