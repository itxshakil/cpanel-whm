<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Events;

use Itxshakil\CpanelWhm\WhmRequest;

/**
 * Fired before every WHM call. The request's parameters are already redacted.
 */
final readonly class WhmRequestSending
{
    public function __construct(public WhmRequest $request) {}
}
