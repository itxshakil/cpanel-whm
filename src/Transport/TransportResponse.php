<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Transport;

/**
 * What came back over the wire, before WHM's envelope is interpreted.
 */
final readonly class TransportResponse
{
    /**
     * @param  array<array-key, mixed>|null  $json
     */
    public function __construct(
        public int $status,
        public ?array $json,
        public string $body = '',
        public float $durationMs = 0.0,
    ) {}

    /**
     * @param  array<array-key, mixed>  $json
     */
    public static function json(array $json, int $status = 200): self
    {
        return new self($status, $json, (string) json_encode($json));
    }
}
