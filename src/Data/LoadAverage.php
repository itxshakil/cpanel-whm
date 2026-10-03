<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

/**
 * The server's load average over 1, 5 and 15 minutes, from systemloadavg.
 */
final readonly class LoadAverage
{
    public function __construct(
        public float $one,
        public float $five,
        public float $fifteen,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $float = static fn (mixed $value): float => is_numeric($value) ? (float) $value : 0.0;

        return new self($float($data['one'] ?? null), $float($data['five'] ?? null), $float($data['fifteen'] ?? null));
    }
}
