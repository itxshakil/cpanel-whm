<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Exceptions;

use Itxshakil\CpanelWhm\WhmResponse;

/**
 * WHM ran the function and reported failure (metadata.result = 0).
 */
class WhmCommandFailed extends WhmException
{
    /**
     * Phrases WHM uses when a token's ACL does not cover a function.
     *
     * @var list<string>
     */
    private const array PERMISSION_PHRASES = [
        'access denied',
        'permission denied',
        'you do not have permission',
        'you do not have access',
        'not have the privilege',
        'requires the privilege',
    ];

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

    /**
     * WhmPermissionDenied when the reason says the token lacks a privilege,
     * WhmCommandFailed otherwise.
     */
    public static function classify(string $function, WhmResponse $response): self
    {
        $reason = mb_strtolower($response->reason());

        foreach (self::PERMISSION_PHRASES as $phrase) {
            if (str_contains($reason, $phrase)) {
                return WhmPermissionDenied::from($function, $response);
            }
        }

        return self::from($function, $response);
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
