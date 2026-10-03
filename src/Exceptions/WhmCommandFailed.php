<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Exceptions;

use Itxshakil\CpanelWhm\WhmResponse;

/**
 * WHM ran the function and reported failure (metadata.result = 0).
 */
class WhmCommandFailed extends WhmException
{
    final public function __construct(
        private readonly string $function,
        private readonly string $reason,
        private readonly WhmResponse $response,
    ) {
        parent::__construct("WHM {$function} failed: {$reason}");
    }

    public static function from(string $function, WhmResponse $response): static
    {
        return new static($function, $response->reason(), $response);
    }

    public function function(): string
    {
        return $this->function;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function response(): WhmResponse
    {
        return $this->response;
    }

    /**
     * The function's detailed log, when WHM sends one (createacct does).
     */
    public function rawOutput(): ?string
    {
        return $this->response->rawOutput();
    }
}
