<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Events;

use Itxshakil\CpanelWhm\WhmRequest;
use Itxshakil\CpanelWhm\WhmResponse;

/**
 * Fired after WHM answered with valid JSON, whether the function succeeded or not.
 */
final readonly class WhmResponseReceived
{
    public function __construct(
        public WhmRequest $request,
        public WhmResponse $response,
        public float $durationMs,
    ) {}
}
