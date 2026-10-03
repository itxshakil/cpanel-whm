<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Exceptions;

/**
 * WHM answered with an HTTP error status, or with something that is not JSON.
 */
class WhmHttpError extends WhmException
{
    final public function __construct(
        string $message,
        private readonly int $status,
        private readonly string $function,
    ) {
        parent::__construct($message, $status);
    }

    public static function forStatus(int $status, string $function, ?string $reason = null): static
    {
        $message = "WHM returned HTTP {$status} for {$function}";

        return new static($reason !== null && $reason !== '' ? "{$message}: {$reason}" : "{$message}.", $status, $function);
    }

    public static function notJson(int $status, string $function): static
    {
        return new static("WHM returned a response to {$function} that is not JSON (HTTP {$status}).", $status, $function);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function function(): string
    {
        return $this->function;
    }

    public function hint(): ?string
    {
        if ($this->getMessage() !== '' && str_contains($this->getMessage(), 'not JSON')) {
            return 'This usually means the host points at a login page or proxy, not WHM. Check the host and port (2087).';
        }

        return $this->status >= 500 ? 'WHM had an internal error. Check /usr/local/cpanel/logs/error_log on the server.' : null;
    }
}
