<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Events;

/**
 * An account's settings changed (modifyacct succeeded).
 */
final readonly class AccountModified
{
    /**
     * @param  array<string, mixed>  $changes  the parameters sent to modifyacct, with secrets redacted
     */
    public function __construct(
        public string $connection,
        public string $user,
        public array $changes,
    ) {}
}
