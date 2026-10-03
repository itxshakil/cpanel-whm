<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Events;

use Itxshakil\CpanelWhm\Data\CreatedAccount;

/**
 * WHM created a cPanel account (createacct succeeded).
 */
final readonly class AccountCreated
{
    public function __construct(
        public string $connection,
        public CreatedAccount $account,
    ) {}
}
