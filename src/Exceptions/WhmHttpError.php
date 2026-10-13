<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Exceptions;

use Itxshakil\CpanelWhm\Support\Redactor;

/**
 * WHM answered with an HTTP error status, or with something that is not JSON.
 */
class WhmHttpError extends WhmException
{
    /**
     * Enough of an error page or JSON error to see what went wrong, without
     * filling a log with a whole HTML document.
     */
    public const int BODY_LIMIT = 2000;

    /**
     * @param  string|null  $body  the response body, already redacted and truncated
     */
    final public function __construct(
        string $message,
        private readonly int $status,
        private readonly string $function,
        private readonly ?string $body = null,
    ) {
        parent::__construct($message, $status);
    }

    public static function forStatus(int $status, string $function, ?string $reason = null, ?string $body = null): static
    {
        $message = "WHM returned HTTP {$status} for {$function}";

        return new static($reason !== null && $reason !== '' ? "{$message}: {$reason}" : "{$message}.", $status, $function, self::safeBody($body));
    }

    public static function notJson(int $status, string $function, ?string $body = null): static
    {
        return new static("WHM returned a response to {$function} that is not JSON (HTTP {$status}).", $status, $function, self::safeBody($body));
    }

    /**
     * The start of the response body, with secrets masked: an error page, a
     * proxy's maintenance page or WHM's JSON error. Null when there was none.
     */
    public function body(): ?string
    {
        return $this->body;
    }

    /**
     * Error pages can echo the request back, so the body goes through the same
     * redaction as events and logs before it is kept.
     */
    private static function safeBody(?string $body): ?string
    {
        if ($body === null || trim($body) === '') {
            return null;
        }

        $json = json_decode($body, true);
        $safe = is_array($json)
            ? (string) json_encode(Redactor::redact($json), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : Redactor::redactString($body);

        return mb_strlen($safe) > self::BODY_LIMIT ? mb_substr($safe, 0, self::BODY_LIMIT).'…' : $safe;
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
