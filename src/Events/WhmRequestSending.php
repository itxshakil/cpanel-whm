<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Events;

use Itxshakil\CpanelWhm\WhmRequest;

/**
 * Fired before every WHM call. Use $request->redactedParams() for anything you log.
 */
final readonly class WhmRequestSending
{
    public function __construct(public WhmRequest $request) {}
}
