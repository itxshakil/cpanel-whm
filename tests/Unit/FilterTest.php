<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Unit;

use InvalidArgumentException;
use Itxshakil\CpanelWhm\Support\Filter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FilterTest extends TestCase
{
    #[Test]
    public function it_builds_api_filter_parameters_with_one_letter_per_condition(): void
    {
        $params = Filter::where('domain', 'contains', 'acme')->andWhere('maxsub', '>=', 10)->toParams();

        self::assertSame([
            'api.filter.enable' => 1,
            'api.filter.a.field' => 'domain',
            'api.filter.a.arg0' => 'acme',
            'api.filter.a.type' => 'contains',
            'api.filter.b.field' => 'maxsub',
            'api.filter.b.arg0' => '10',
            'api.filter.b.type' => 'gt_equal',
        ], $params);
    }

    #[Test]
    public function equals_maps_to_eq(): void
    {
        self::assertSame('eq', Filter::where('plan', '=', 'starter')->toParams()['api.filter.a.type']);
    }

    #[Test]
    public function unknown_operators_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown WHM filter operator');

        Filter::where('plan', 'like', 'x');
    }

    #[Test]
    public function it_allows_at_most_26_conditions(): void
    {
        $filter = Filter::where('a', 'eq', 0);

        for ($i = 1; $i < 26; $i++) {
            $filter->andWhere('a', 'eq', $i);
        }

        self::assertFalse($filter->isEmpty());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at most 26');

        $filter->andWhere('a', 'eq', 26);
    }
}
