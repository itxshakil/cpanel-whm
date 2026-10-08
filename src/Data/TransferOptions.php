<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

/**
 * How a transfer session runs. cPanel requires every one of these flags;
 * the defaults match WHM's Transfer Tool.
 */
final readonly class TransferOptions
{
    public function __construct(
        public bool $compressed = true,
        public bool $unencrypted = false,
        public bool $lowPriority = false,
        public bool $useBackups = false,
        public bool $copyResellerPrivileges = true,
        public bool $customPkgacct = false,
        public bool $unrestrictedRestore = false,
        public int $transferThreads = 1,
        public int $restoreThreads = 1,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toParams(): array
    {
        return [
            'comm_transport' => 'ssh',
            'compressed' => $this->compressed,
            'copy_reseller_privs' => $this->copyResellerPrivileges,
            'enable_custom_pkgacct' => $this->customPkgacct,
            'low_priority' => $this->lowPriority,
            'restore_threads' => $this->restoreThreads,
            'transfer_threads' => $this->transferThreads,
            'unencrypted' => $this->unencrypted,
            'unrestricted_restore' => $this->unrestrictedRestore,
            'use_backups' => $this->useBackups,
        ];
    }
}
