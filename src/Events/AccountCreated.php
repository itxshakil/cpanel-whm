<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Events;

use Itxshakil\CpanelWhm\Data\CreatedAccount;

/**
 * WHM created a cPanel account (createacct succeeded). The account's
 * password is never included: $account->password is null.
 */
final readonly class AccountCreated
{
    public function __construct(
        public string $connection,
        public CreatedAccount $account,
    ) {}
}
