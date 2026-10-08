<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Generator;

use Itxshakil\CpanelWhm\Support\Redactor;

final readonly class Parameter
{
    /**
     * @param  list<string>  $types  PHP types without null: array, bool, float, int, string or mixed
     */
    public function __construct(
        public string $name,
        public string $variable,
        public bool $required,
        public array $types,
        public string $description,
    ) {}

    public function isMixed(): bool
    {
        return in_array('mixed', $this->types, true);
    }

    public function nativeType(): string
    {
        if ($this->isMixed()) {
            return 'mixed';
        }

        $type = implode('|', $this->types);

        if ($this->required) {
            return $type;
        }

        return count($this->types) === 1 ? '?'.$type : $type.'|null';
    }

    public function docType(): string
    {
        if ($this->isMixed()) {
            return 'mixed';
        }

        $types = array_map(static fn (string $type): string => $type === 'array' ? 'array<array-key, mixed>' : $type, $this->types);

        if (! $this->required) {
            $types[] = 'null';
        }

        return implode('|', $types);
    }

    public function needsDocType(): bool
    {
        return in_array('array', $this->types, true);
    }

    /**
     * Passwords, tokens and keys are marked so PHP hides them in stack traces.
     */
    public function isSensitive(): bool
    {
        return Redactor::isSensitiveKey($this->name);
    }

    public function signature(): string
    {
        return ($this->isSensitive() ? '#[\\SensitiveParameter] ' : '').$this->nativeType().' $'.$this->variable.($this->required ? '' : ' = null');
    }
}
