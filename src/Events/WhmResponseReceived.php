<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Events;

use Itxshakil\CpanelWhm\WhmRequest;
use Itxshakil\CpanelWhm\WhmResponse;

/**
 * Fired after WHM answered with a successful call. The request and the
 * response are redacted copies: new tokens, login URLs and passwords are masked.
 */
final readonly class WhmResponseReceived
{
    public function __construct(
        public WhmRequest $request,
        public WhmResponse $response,
        public float $durationMs,
    ) {}
}
