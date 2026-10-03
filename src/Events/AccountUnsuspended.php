<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Events;

/**
 * WHM unsuspended a cPanel account.
 */
final readonly class AccountUnsuspended
{
    public function __construct(
        public string $connection,
        public string $user,
    ) {}
}
