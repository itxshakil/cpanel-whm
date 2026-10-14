<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;

/**
 * Base for the hand-written helpers that run as one cPanel account, such as
 * Whm::asUser('acme')->email(). They are macroable like the WHM modules.
 */
abstract class UserModule
{
    use Macroable;

    public function __construct(protected readonly CpanelUser $user) {}

    /**
     * UAPI's pagination parameters for one page, or none without $page.
     *
     * @return array<string, int>
     *
     * @throws InvalidArgumentException when $page or $perPage is below 1
     */
    protected static function page(?int $page, int $perPage): array
    {
        if ($page === null) {
            return [];
        }

        if ($page < 1 || $perPage < 1) {
            throw new InvalidArgumentException("UAPI pages start at 1 and hold at least one row; got page {$page} of {$perPage}.");
        }

        return ['api.paginate' => 1, 'api.paginate_page' => $page, 'api.paginate_size' => $perPage];
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    protected static function rows(mixed $rows): array
    {
        return is_array($rows) ? array_values(array_filter($rows, is_array(...))) : [];
    }
}
