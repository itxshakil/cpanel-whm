<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm;

use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Support\Redactor;

/**
 * One WHM API 1 call, before it is sent.
 */
final readonly class WhmRequest
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public string $connection,
        public string $function,
        public array $params = [],
        public HttpMethod $method = HttpMethod::Get,
        public ?int $timeout = null,
    ) {}

    /**
     * @return array<array-key, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'connection' => $this->connection,
            'function' => $this->function,
            'params' => $this->redactedParams(),
            'method' => $this->method->value,
            'timeout' => $this->timeout,
        ];
    }

    /**
     * The parameters with passwords, tokens and session ids masked. Use this for
     * anything that is logged or displayed.
     *
     * @return array<array-key, mixed>
     */
    public function redactedParams(): array
    {
        return Redactor::redact($this->params);
    }
}
