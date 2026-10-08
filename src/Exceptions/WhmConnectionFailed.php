<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Exceptions;

use Itxshakil\CpanelWhm\Support\ConnectionConfig;
use Throwable;

/**
 * The server could not be reached, or stopped answering: DNS, refused
 * connection, TLS failure or timeout.
 *
 * Usually nothing reached WHM. When the connection was open and WHM did not
 * answer in time, it may have acted anyway; mayHaveReachedServer() says so,
 * and a change should be checked before it is repeated.
 */
final class WhmConnectionFailed extends WhmException
{
    /**
     * cURL errors raised after the request was sent: 28 without "Connection"
     * or "Resolving" (a read timeout), 52 (empty reply) and 56 (receive failure).
     */
    private const string AFTER_SENDING = '/cURL error (?:28: (?!Connection|Resolving)|52:|56:)/i';

    private function __construct(string $message, private readonly bool $mayHaveReachedServer)
    {
        parent::__construct($message);
    }

    /**
     * The original exception is not chained: its message and the HTTP request
     * inside it hold the full URL and the Authorization header.
     */
    public static function to(ConnectionConfig $config, string $function, ?Throwable $previous = null): self
    {
        $original = $previous instanceof Throwable ? $previous->getMessage() : 'connection failed';

        return new self(
            "Could not reach WHM at {$config->host}:{$config->port} while calling {$function}: ".self::sanitise($original),
            preg_match(self::AFTER_SENDING, $original) === 1,
        );
    }

    /**
     * cURL messages quote the full URL, query string included. GET parameters can
     * carry anything a caller passed, so the query string is dropped.
     */
    public static function sanitise(string $message): string
    {
        return (string) preg_replace('/\?\S*/', '?[redacted]', $message);
    }

    /**
     * Whether WHM may have received the call and acted on it before the
     * connection failed. Check before repeating a change such as createacct.
     */
    public function mayHaveReachedServer(): bool
    {
        return $this->mayHaveReachedServer;
    }

    public function hint(): string
    {
        if ($this->mayHaveReachedServer) {
            return 'WHM did not answer in time, but it may have received the call and acted on it. Check whether the change was made before repeating it; for slow functions, raise the timeout.';
        }

        return 'Check the host and that port 2087 is open to this server (firewall, cPHulk). Run php artisan whm:doctor for a step-by-step check.';
    }
}
