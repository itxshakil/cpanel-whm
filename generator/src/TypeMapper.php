<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Generator;

/**
 * OpenAPI schema -> PHP parameter types.
 *
 * Integers documented as 0/1 flags also accept bool (sent as 1/0). A list
 * parameter is sent the way WHM expects: name, name-1, name-2, ...
 */
final readonly class TypeMapper
{
    private const array ORDER = ['array', 'bool', 'float', 'int', 'string'];

    public function __construct(private Spec $spec) {}

    /**
     * @param  array<array-key, mixed>  $schema
     * @return list<string>
     */
    public function map(array $schema): array
    {
        $types = $this->collect($this->spec->resolve($schema), 0);

        if ($types === [] || in_array('mixed', $types, true)) {
            return ['mixed'];
        }

        $types = array_values(array_unique($types));
        usort($types, static fn (string $a, string $b): int => array_search($a, self::ORDER, true) <=> array_search($b, self::ORDER, true));

        return $types;
    }

    /**
     * @param  array<array-key, mixed>  $schema
     * @return list<string>
     */
    private function collect(array $schema, int $depth): array
    {
        if ($depth > 6) {
            return ['mixed'];
        }

        foreach (['oneOf', 'anyOf'] as $keyword) {
            if (is_array($schema[$keyword] ?? null)) {
                $types = [];

                foreach ($schema[$keyword] as $option) {
                    if (is_array($option)) {
                        $types = [...$types, ...$this->collect($this->spec->resolve($option), $depth + 1)];
                    }
                }

                return $types;
            }
        }

        if (is_array($schema['allOf'] ?? null) && count($schema['allOf']) === 1 && is_array($schema['allOf'][0])) {
            return $this->collect($this->spec->resolve($schema['allOf'][0]), $depth + 1);
        }

        $type = $schema['type'] ?? null;
        $enum = is_array($schema['enum'] ?? null) ? $schema['enum'] : null;

        if (is_array($type)) {
            $types = [];

            foreach ($type as $single) {
                if (is_string($single) && $single !== 'null') {
                    $types = [...$types, ...$this->collect(['type' => $single, 'enum' => $enum], $depth + 1)];
                }
            }

            return $types;
        }

        return match ($type) {
            'string' => ['string'],
            'integer' => $this->isFlag($enum) ? ['bool', 'int'] : ['int'],
            'number' => ['float', 'int'],
            'boolean' => ['bool'],
            'array', 'object' => ['array'],
            default => $this->fromEnum($enum, $schema),
        };
    }

    /**
     * @param  array<array-key, mixed>|null  $enum
     */
    private function isFlag(?array $enum): bool
    {
        if ($enum === null || $enum === []) {
            return false;
        }

        foreach ($enum as $value) {
            if (! in_array($value, [0, 1], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<array-key, mixed>|null  $enum
     * @param  array<array-key, mixed>  $schema
     * @return list<string>
     */
    private function fromEnum(?array $enum, array $schema): array
    {
        if (isset($schema['items'])) {
            return ['array'];
        }

        if ($enum === null) {
            return ['mixed'];
        }

        $values = array_values(array_filter($enum, static fn (mixed $value): bool => $value !== null));

        if ($values === []) {
            return [];    // enum: [null] only adds nullability
        }

        if ($this->isFlag($values)) {
            return ['bool', 'int'];
        }

        $types = [];

        foreach ($values as $value) {
            $types[] = match (true) {
                is_int($value) => 'int',
                is_float($value) => 'float',
                is_bool($value) => 'bool',
                is_string($value) => 'string',
                default => 'mixed',
            };
        }

        return $types;
    }
}
