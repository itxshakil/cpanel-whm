<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

/**
 * A UAPI function's own envelope, unwrapped from uapi_cpanel's data.uapi.
 */
final readonly class UapiResult
{
    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     * @param  list<string>  $messages
     * @param  array<array-key, mixed>  $metadata
     */
    public function __construct(
        public bool $successful,
        public mixed $data,
        public array $errors = [],
        public array $warnings = [],
        public array $messages = [],
        public array $metadata = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $uapi
     */
    public static function fromArray(array $uapi): self
    {
        return new self(
            successful: Value::bool($uapi['status'] ?? false),
            data: $uapi['data'] ?? null,
            errors: Value::strings($uapi['errors'] ?? null),
            warnings: Value::strings($uapi['warnings'] ?? null),
            messages: Value::strings($uapi['messages'] ?? null),
            metadata: is_array($uapi['metadata'] ?? null) ? $uapi['metadata'] : [],
        );
    }

    /**
     * Read from data with dot notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    /**
     * The data as an array (UAPI functions return a list or an object).
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return is_array($this->data) ? $this->data : [];
    }
}
