<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Support;

/**
 * Turns PHP values into the form WHM reads.
 *
 * - null is left out, so optional arguments that were not given are not sent;
 * - true/false become 1/0;
 * - a list becomes WHM's repeated parameters: name, name-1, name-2, ...
 *
 * Associative arrays are passed through unchanged.
 */
final class Params
{
    /**
     * @param  array<array-key, mixed>  $params
     * @return array<string, mixed>
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
                foreach ($value as $index => $item) {
                    if ($item !== null) {
                        $out[$index === 0 ? $key : $key.'-'.$index] = self::scalar($item);
                    }
                }

                continue;
            }

            $out[$key] = self::scalar($value);
        }

        return $out;
    }

    private static function scalar(mixed $value): mixed
    {
        return is_bool($value) ? (int) $value : $value;
    }
}
