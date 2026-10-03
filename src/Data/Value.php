<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

use Carbon\CarbonImmutable;

/**
 * Tolerant readers for WHM's loosely typed JSON (numbers as strings, 0/1 booleans).
 *
 * @internal
 */
final class Value
{
    public static function string(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return is_int($value) || is_float($value) ? (string) $value : null;
    }

    public static function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return is_string($value) && in_array(mb_strtolower($value), ['y', 'yes', 'true', 'on'], true);
    }

    public static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    public static function timestamp(mixed $value): ?CarbonImmutable
    {
        return is_numeric($value) && (int) $value > 0 ? CarbonImmutable::createFromTimestampUTC((int) $value) : null;
    }

    /**
     * @return list<string>
     */
    public static function strings(mixed $value): array
    {
        if (is_string($value)) {
            return $value === '' ? [] : [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(strval(...), array_filter($value, is_scalar(...))));
    }
}
