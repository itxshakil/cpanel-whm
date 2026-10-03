<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Support;

use InvalidArgumentException;

/**
 * WHM API 1 output filtering (api.filter.*), usable with any list function.
 *
 *     Filter::where('domain', 'contains', 'acme')->andWhere('plan', 'eq', 'starter')
 */
final class Filter
{
    /**
     * @var array<string, string>
     */
    public const array OPERATORS = [
        'contains' => 'contains',
        'begins' => 'begins',
        'eq' => 'eq',
        '=' => 'eq',
        '==' => '==',
        'lt' => 'lt',
        '<' => 'lt',
        'lt_equal' => 'lt_equal',
        '<=' => 'lt_equal',
        'gt' => 'gt',
        '>' => 'gt',
        'gt_equal' => 'gt_equal',
        '>=' => 'gt_equal',
        'lt_handle_unlimited' => 'lt_handle_unlimited',
        'gt_handle_unlimited' => 'gt_handle_unlimited',
    ];

    /**
     * @var list<array{field: string, type: string, value: string}>
     */
    private array $conditions = [];

    public static function where(string $field, string $operator, string|int|float $value): self
    {
        return (new self)->andWhere($field, $operator, $value);
    }

    /**
     * Add another condition. WHM applies all of them (AND).
     */
    public function andWhere(string $field, string $operator, string|int|float $value): self
    {
        $type = self::OPERATORS[mb_strtolower($operator)] ?? null;

        if ($type === null) {
            throw new InvalidArgumentException("Unknown WHM filter operator [{$operator}].");
        }

        if (count($this->conditions) >= 26) {
            throw new InvalidArgumentException('WHM supports at most 26 filters per call.');
        }

        $this->conditions[] = ['field' => $field, 'type' => $type, 'value' => (string) $value];

        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->conditions === [];
    }

    /**
     * @return array<string, string|int>
     */
    public function toParams(): array
    {
        if ($this->conditions === []) {
            return [];
        }

        $params = ['api.filter.enable' => 1];

        foreach ($this->conditions as $index => $condition) {
            $letter = chr(ord('a') + $index);
            $params["api.filter.{$letter}.field"] = $condition['field'];
            $params["api.filter.{$letter}.arg0"] = $condition['value'];
            $params["api.filter.{$letter}.type"] = $condition['type'];
        }

        return $params;
    }
}
