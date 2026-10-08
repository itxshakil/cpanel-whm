<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

use Carbon\CarbonImmutable;

/**
 * A mailbox, from UAPI Email::list_pops_with_disk. Sizes are in bytes; a
 * null quota means unlimited.
 */
final readonly class EmailAccount
{
    /**
     * @param  array<array-key, mixed>  $raw  everything cPanel returned
     */
    public function __construct(
        public string $address,
        public string $user,
        public string $domain,
        public int $usedBytes,
        public ?int $quotaBytes,
        public bool $loginSuspended,
        public bool $incomingSuspended,
        public bool $outgoingSuspended,
        public bool $outgoingHeld,
        public ?CarbonImmutable $modifiedAt,
        public array $raw,
    ) {}

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        $address = Value::string($row['email'] ?? null) ?? '';
        $quota = Value::int($row['_diskquota'] ?? null);
        $modified = Value::int($row['mtime'] ?? null);

        return new self(
            address: mb_strtolower($address),
            user: Value::string($row['user'] ?? null) ?? (string) strstr($address, '@', true),
            domain: Value::string($row['domain'] ?? null) ?? ltrim((string) strstr($address, '@'), '@'),
            usedBytes: Value::int($row['_diskused'] ?? null) ?? 0,
            quotaBytes: $quota !== null && $quota > 0 ? $quota : null,
            loginSuspended: Value::bool($row['suspended_login'] ?? false),
            incomingSuspended: Value::bool($row['suspended_incoming'] ?? false),
            outgoingSuspended: Value::bool($row['suspended_outgoing'] ?? false),
            outgoingHeld: Value::bool($row['hold_outgoing'] ?? false),
            modifiedAt: $modified !== null && $modified > 0 ? CarbonImmutable::createFromTimestamp($modified) : null,
            raw: $row,
        );
    }

    public function isUnlimited(): bool
    {
        return $this->quotaBytes === null;
    }

    /**
     * Share of the quota in use, 0-100, or null when unlimited.
     */
    public function percentUsed(): ?float
    {
        return $this->quotaBytes === null ? null : round($this->usedBytes / $this->quotaBytes * 100, 1);
    }

    public function isSuspended(): bool
    {
        return $this->loginSuspended || $this->incomingSuspended || $this->outgoingSuspended;
    }
}
