<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use Illuminate\Support\Traits\Macroable;

/**
 * Base for the hand-written helpers that run as one cPanel account, such as
 * Whm::asUser('acme')->email(). They are macroable like the WHM modules.
 */
abstract class UserModule
{
    use Macroable;

    public function __construct(protected readonly CpanelUser $user) {}

    /**
     * @return list<array<array-key, mixed>>
     */
    protected static function rows(mixed $rows): array
    {
        return is_array($rows) ? array_values(array_filter($rows, is_array(...))) : [];
    }
}
