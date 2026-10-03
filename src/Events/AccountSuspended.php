<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Events;

/**
 * WHM suspended a cPanel account.
 */
final readonly class AccountSuspended
{
    public function __construct(
        public string $connection,
        public string $user,
        public string $reason = '',
        public bool $locked = false,
    ) {}
}
