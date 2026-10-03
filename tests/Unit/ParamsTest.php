<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Unit;

use Itxshakil\CpanelWhm\Support\Params;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParamsTest extends TestCase
{
    #[Test]
    public function nulls_are_dropped_and_booleans_become_flags(): void
    {
        self::assertSame(
            ['user' => 'acme', 'keepdns' => 1, 'force' => 0, 'count' => 0],
            Params::normalise(['user' => 'acme', 'domain' => null, 'keepdns' => true, 'force' => false, 'count' => 0]),
        );
    }

    #[Test]
    public function lists_become_whms_repeated_parameters(): void
    {
        self::assertSame(
            ['zone' => 'a.test', 'zone-1' => 'b.test', 'zone-2' => 'c.test', 'flag' => 1, 'flag-1' => 0],
            Params::normalise(['zone' => ['a.test', 'b.test', 'c.test'], 'flag' => [true, false]]),
        );
    }

    #[Test]
    public function empty_lists_are_not_sent_and_maps_pass_through(): void
    {
        self::assertSame(['options' => ['a' => 1]], Params::normalise(['remove' => [], 'options' => ['a' => 1]]));
    }
}
