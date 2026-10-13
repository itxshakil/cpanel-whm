<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Exceptions;

use RuntimeException;

/**
 * A safe stand-in for the HTTP client's own exception, kept as the previous
 * exception of WhmConnectionFailed. The original can't be chained: its message
 * quotes the full URL and the request inside it carries the Authorization
 * header. This keeps what error trackers need: the class, the cURL error
 * number (as the code) and a sanitised message.
 */
final class TransportFailure extends RuntimeException
{
    /**
     * @param  class-string  $originalClass
     */
    public function __construct(
        private readonly string $originalClass,
        string $message,
        int $curlError = 0,
    ) {
        parent::__construct($message, $curlError);
    }

    /**
     * The class of the exception the HTTP client threw, e.g.
     * Illuminate\Http\Client\ConnectionException.
     *
     * @return class-string
     */
    public function originalClass(): string
    {
        return $this->originalClass;
    }

    /**
     * The cURL error number (28 timeout, 7 refused, 6 DNS, ...), or null when the message had none.
     */
    public function curlError(): ?int
    {
        return $this->getCode() === 0 ? null : $this->getCode();
    }
}
