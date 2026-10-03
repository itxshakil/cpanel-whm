<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Support;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Log\LogManager;
use Itxshakil\CpanelWhm\Events\WhmRequestFailed;
use Itxshakil\CpanelWhm\Events\WhmResponseReceived;

/**
 * Logs WHM calls to the channel named in cpanel-whm.log_channel. Parameters
 * are redacted; response bodies are not logged.
 */
final readonly class RequestLogger
{
    public function __construct(
        private LogManager $log,
        private string $channel,
    ) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(WhmResponseReceived::class, $this->received(...));
        $events->listen(WhmRequestFailed::class, $this->failed(...));
    }

    public function received(WhmResponseReceived $event): void
    {
        $this->log->channel($this->channel)->info("WHM {$event->request->function}: {$event->response->reason()}", [
            'connection' => $event->request->connection,
            'function' => $event->request->function,
            'params' => $event->request->redactedParams(),
            'duration_ms' => (int) round($event->durationMs),
            'warnings' => $event->response->warnings(),
        ]);
    }

    public function failed(WhmRequestFailed $event): void
    {
        $this->log->channel($this->channel)->warning("WHM {$event->request->function} failed: {$event->exception->getMessage()}", [
            'connection' => $event->request->connection,
            'function' => $event->request->function,
            'params' => $event->request->redactedParams(),
            'exception' => $event->exception::class,
        ]);
    }
}
