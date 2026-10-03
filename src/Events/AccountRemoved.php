<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Events;

/**
 * WHM deleted a cPanel account (removeacct succeeded).
 */
final readonly class AccountRemoved
{
    public function __construct(
        public string $connection,
        public string $user,
    ) {}
}
