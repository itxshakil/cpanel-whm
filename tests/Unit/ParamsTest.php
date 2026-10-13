<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Unit;

use DateTimeImmutable;
use InvalidArgumentException;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Support\Params;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;

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
    public function nulls_in_a_list_leave_no_gap_in_the_numbering(): void
    {
        self::assertSame(
            ['zone' => 'a.test', 'zone-1' => 'c.test', 'user' => 'acme'],
            Params::normalise(['zone' => [null, 'a.test', null, 'c.test'], 'user' => 'acme']),
        );
    }

    #[Test]
    public function empty_lists_are_not_sent(): void
    {
        self::assertSame(['user' => 'acme'], Params::normalise(['remove' => [], 'user' => 'acme']));
    }

    #[Test]
    public function backed_enums_and_stringables_become_strings(): void
    {
        $stringable = new class implements Stringable
        {
            public function __toString(): string
            {
                return 'acme.test';
            }
        };

        self::assertSame(
            ['method' => 'POST', 'domain' => 'acme.test', 'methods' => 'GET', 'methods-1' => 'POST'],
            Params::normalise(['method' => HttpMethod::Post, 'domain' => $stringable, 'methods' => [HttpMethod::Get, HttpMethod::Post]]),
        );
    }

    /**
     * WHM reads flat form fields, so these would be sent as name[key]=... and
     * ignored, or fail inside the HTTP client.
     */
    #[Test]
    #[DataProvider('unsupportedValues')]
    public function values_whm_cannot_read_are_refused(mixed $value, string $mentions): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/\[options\].*'.preg_quote($mentions, '/').'/');

        Params::normalise(['options' => $value]);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function unsupportedValues(): iterable
    {
        yield 'a map' => [['skipbwdata' => 1], 'nested array'];
        yield 'a list of maps' => [[['a' => 1]], 'nested array'];
        yield 'a date' => [new DateTimeImmutable('2026-10-01'), 'DateTimeImmutable'];
        yield 'an object' => [new stdClass, 'stdClass'];
    }
}
