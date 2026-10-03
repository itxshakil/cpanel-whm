<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Exceptions;

use Itxshakil\CpanelWhm\Support\ConnectionConfig;
use Throwable;

/**
 * The server could not be reached: DNS, refused connection, TLS failure or timeout.
 * Nothing reached WHM, so a read can safely be retried.
 */
final class WhmConnectionFailed extends WhmException
{
    public static function to(ConnectionConfig $config, string $function, ?Throwable $previous = null): self
    {
        $reason = $previous instanceof Throwable ? self::sanitise($previous->getMessage()) : 'connection failed';

        return new self(
            "Could not reach WHM at {$config->host}:{$config->port} while calling {$function}: {$reason}",
            0,
            $previous,
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

    public function hint(): string
    {
        return 'Check the host and that port 2087 is open to this server (firewall, cPHulk). Run php artisan whm:doctor for a step-by-step check.';
    }
}
