<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * A domain AutoSSL could not secure, and why (usually a failed DCV check).
 */
final readonly class AutoSslProblem
{
    public function __construct(
        public string $domain,
        public string $problem,
        public ?CarbonImmutable $time,
    ) {}

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        $time = Value::string($row['time'] ?? null);

        try {
            $parsed = $time === null ? null : CarbonImmutable::parse($time);
        } catch (Throwable) {
            $parsed = null;
        }

        return new self(
            domain: Value::string($row['domain'] ?? null) ?? '',
            problem: Value::string($row['problem'] ?? null) ?? '',
            time: $parsed,
        );
    }
}
