<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Events;

use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\WhmRequest;

/**
 * Fired when a call throws: connection failure, HTTP error or WHM failure.
 */
final readonly class WhmRequestFailed
{
    public function __construct(
        public WhmRequest $request,
        public WhmException $exception,
    ) {}
}
