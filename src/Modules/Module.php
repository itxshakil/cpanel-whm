<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use Illuminate\Support\Traits\Macroable;
use Itxshakil\CpanelWhm\Contracts\WhmClient;

/**
 * Base for the curated modules. Modules are macroable, so an app can add its
 * own helpers: Accounts::macro('byOwner', fn (string $owner) => ...).
 */
abstract class Module
{
    use Macroable;

    public function __construct(protected readonly WhmClient $client) {}

    /**
     * @return list<array<array-key, mixed>>
     */
    protected function rows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, is_array(...)));
    }
}
