<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Support;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Log\LogManager;
use Itxshakil\CpanelWhm\Events\WhmRequestFailed;
use Itxshakil\CpanelWhm\Events\WhmResponseReceived;
use Itxshakil\CpanelWhm\Exceptions\UapiCallFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmCommandFailed;

/**
 * Logs WHM calls to the channel named in cpanel-whm.log_channel. Parameters
 * are redacted. Response bodies are logged only with log_responses, and are
 * redacted too.
 */
final readonly class RequestLogger
{
    /**
     * The defaults keep the levels the logger has always used.
     *
     * @var array{success: string, command_failed: string, error: string}
     */
    public const array DEFAULT_LEVELS = [
        'success' => 'info',
        'command_failed' => 'warning',
        'error' => 'warning',
    ];

    /**
     * @var array{success: string, command_failed: string, error: string}
     */
    private array $levels;

    /**
     * @param  array<array-key, mixed>  $levels  success, command_failed (WHM or UAPI said no) and error (HTTP, connection)
     */
    public function __construct(
        private LogManager $log,
        private string $channel,
        private bool $logResponses = false,
        array $levels = [],
    ) {
        $this->levels = [
            'success' => self::level($levels['success'] ?? null, self::DEFAULT_LEVELS['success']),
            'command_failed' => self::level($levels['command_failed'] ?? null, self::DEFAULT_LEVELS['command_failed']),
            'error' => self::level($levels['error'] ?? null, self::DEFAULT_LEVELS['error']),
        ];
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(WhmResponseReceived::class, $this->received(...));
        $events->listen(WhmRequestFailed::class, $this->failed(...));
    }

    public function received(WhmResponseReceived $event): void
    {
        $context = [
            'connection' => $event->request->connection,
            'function' => $event->request->function,
            'params' => $event->request->redactedParams(),
            'duration_ms' => (int) round($event->durationMs),
            'warnings' => $event->response->warnings(),
        ];

        if ($this->logResponses) {
            // The event already carries a redacted copy of the response.
            $context['response'] = $event->response->toArray();
        }

        $this->log->channel($this->channel)->log($this->levels['success'], "WHM {$event->request->function}: {$event->response->reason()}", $context);
    }

    public function failed(WhmRequestFailed $event): void
    {
        $exception = $event->exception;
        $isCommandFailure = $exception instanceof WhmCommandFailed || $exception instanceof UapiCallFailed;

        $context = [
            'connection' => $event->request->connection,
            'function' => $event->request->function,
            'params' => $event->request->redactedParams(),
            'exception' => $exception::class,
        ];

        if ($this->logResponses) {
            // Redacted here: a failed call's exception holds the raw answer.
            $context['response'] = match (true) {
                $exception instanceof WhmCommandFailed => $exception->response()->redacted()->toArray(),
                $exception instanceof UapiCallFailed => Redactor::redact([
                    'errors' => $exception->errors(),
                    'data' => $exception->result()->toArray(),
                ]),
                default => null,
            };
        }

        $this->log->channel($this->channel)->log(
            $this->levels[$isCommandFailure ? 'command_failed' : 'error'],
            "WHM {$event->request->function} failed: {$exception->getMessage()}",
            $context,
        );
    }

    private static function level(mixed $level, string $default): string
    {
        return in_array($level, ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'], true) ? $level : $default;
    }
}
