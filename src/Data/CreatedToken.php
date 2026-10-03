<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

use SensitiveParameter;

/**
 * A newly created API token. $token is the secret: WHM shows it only once,
 * so store it now. It is hidden from var_dump() and print_r().
 */
final readonly class CreatedToken
{
    public function __construct(
        public ApiToken $details,
        #[SensitiveParameter]
        public string $token,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(string $name, array $data): self
    {
        return new self(ApiToken::fromArray($name, $data), Value::string($data['token'] ?? null) ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['details' => $this->details, 'token' => '[REDACTED]'];
    }
}
