<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Support;

use BackedEnum;
use InvalidArgumentException;
use Stringable;

/**
 * Turns PHP values into the form WHM reads.
 *
 * - null is left out, so optional arguments that were not given are not sent;
 * - true/false become 1/0;
 * - a backed enum becomes its value, a Stringable its string;
 * - a list becomes WHM's repeated parameters: name, name-1, name-2, ...
 *
 * Anything else that isn't a scalar is refused. WHM reads flat form fields, so
 * a nested array would arrive as name[key]=... and be ignored without an error.
 * Dates are refused too: WHM takes them in different formats per function.
 *
 * @internal
 */
final class Params
{
    /**
     * @param  array<array-key, mixed>  $params
     * @return array<string, int|float|string>
     *
     * @throws InvalidArgumentException when a value can't be sent as a form field
     */
    public static function normalise(array $params): array
    {
        $out = [];

        foreach ($params as $key => $value) {
            $key = (string) $key;

            if ($value === null) {
                continue;
            }

            if (is_array($value) && array_is_list($value)) {
                // Nulls are dropped before numbering: WHM reads name, name-1, name-2
                // in sequence, so a gap would hide everything after it.
                $items = array_values(array_filter($value, static fn (mixed $item): bool => $item !== null));

                foreach ($items as $index => $item) {
                    $out[$index === 0 ? $key : $key.'-'.$index] = self::scalar($key, $item);
                }

                continue;
            }

            $out[$key] = self::scalar($key, $value);
        }

        return $out;
    }

    private static function scalar(string $key, mixed $value): int|float|string
    {
        return match (true) {
            is_bool($value) => (int) $value,
            is_scalar($value) => $value,
            $value instanceof BackedEnum => $value->value,
            $value instanceof Stringable => (string) $value,
            is_array($value) => throw new InvalidArgumentException("WHM parameter [{$key}] is a nested array. WHM reads flat form fields: send each value as its own parameter, or JSON-encode it if the function documents JSON."),
            default => throw new InvalidArgumentException("WHM parameter [{$key}] is a ".get_debug_type($value).'. Send a scalar; format dates the way the function documents them.'),
        };
    }
}
