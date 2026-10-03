<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Events;

/**
 * An account's password changed. The password itself is never part of the event.
 */
final readonly class AccountPasswordChanged
{
    public function __construct(
        public string $connection,
        public string $user,
    ) {}
}
