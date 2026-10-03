<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Generator;

final readonly class Operation
{
    /**
     * @param  list<Parameter>  $parameters
     */
    public function __construct(
        public string $function,
        public string $group,
        public string $method,
        public string $summary,
        public string $description,
        public bool $readOnly,
        public bool $deprecated,
        public array $parameters,
        public string $docsUrl,
        public ?string $skipReason = null,
        public ?string $module = null,
        public string $title = '',
    ) {}

    public function key(): string
    {
        return $this->module === null ? $this->function : $this->module.'::'.$this->function;
    }

    public function generated(): bool
    {
        return $this->skipReason === null;
    }

    /**
     * @return list<Parameter>
     */
    public function orderedParameters(): array
    {
        $required = array_values(array_filter($this->parameters, static fn (Parameter $p): bool => $p->required));
        $optional = array_values(array_filter($this->parameters, static fn (Parameter $p): bool => ! $p->required));

        return [...$required, ...$optional];
    }
}
