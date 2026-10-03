<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Events;

/**
 * An account moved to another hosting package.
 */
final readonly class AccountPackageChanged
{
    public function __construct(
        public string $connection,
        public string $user,
        public string $package,
    ) {}
}
