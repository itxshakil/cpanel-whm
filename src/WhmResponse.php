<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm;

use Itxshakil\CpanelWhm\Support\Redactor;

/**
 * WHM API 1's envelope: { "data": {...}, "metadata": { "result", "reason", "command", "version", "output" } }.
 */
final readonly class WhmResponse
{
    /**
     * @param  array<array-key, mixed>  $data
     * @param  array<array-key, mixed>  $metadata
     */
    public function __construct(
        public array $data = [],
        public array $metadata = [],
        public int $status = 200,
    ) {}

    /**
     * @param  array<array-key, mixed>  $json
     */
    public static function fromArray(array $json, int $status = 200): self
    {
        return new self(
            data: is_array($json['data'] ?? null) ? $json['data'] : [],
            metadata: is_array($json['metadata'] ?? null) ? $json['metadata'] : [],
            status: $status,
        );
    }

    /**
     * A copy with secrets masked: new API tokens, login URLs, passwords.
     * Events carry this copy; the caller gets the real response.
     */
    public function redacted(): self
    {
        return new self(Redactor::redact($this->data), Redactor::redact($this->metadata), $this->status);
    }

    public function successful(): bool
    {
        $result = $this->metadata['result'] ?? 0;

        return is_numeric($result) && (int) $result === 1;
    }

    public function failed(): bool
    {
        return ! $this->successful();
    }

    public function command(): ?string
    {
        $command = $this->metadata['command'] ?? null;

        return is_string($command) ? $command : null;
    }

    /**
     * Why WHM refused the call, or "OK".
     *
     * Some functions leave metadata.reason empty and put the reason under
     * data.reason. Falling back to "OK" there once turned a real failure into an
     * alert that just said "OK", so the fallback is only used on success.
     */
    public function reason(): string
    {
        foreach ([$this->metadata['reason'] ?? null, $this->data['reason'] ?? null] as $reason) {
            if (is_string($reason) && trim($reason) !== '') {
                return trim($reason);
            }
        }

        return $this->successful() ? 'OK' : 'WHM reported a failure without a reason.';
    }

    /**
     * Warnings WHM attached to an otherwise successful call. WHM can return
     * result 1 while refusing part of the request (for example "You cannot
     * change backup settings."), so check these when it matters.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->outputList('warnings');
    }

    /**
     * @return list<string>
     */
    public function messages(): array
    {
        return $this->outputList('messages');
    }

    /**
     * The function's detailed log, when WHM sends one (createacct does).
     */
    public function rawOutput(): ?string
    {
        $output = $this->metadata['output'] ?? null;
        $raw = is_array($output) ? ($output['raw'] ?? null) : null;

        return is_string($raw) && $raw !== '' ? $raw : null;
    }

    /**
     * Read a value from data with dot notation: $response->get('acct.0.user').
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    /**
     * @return array{data: array<array-key, mixed>, metadata: array<array-key, mixed>, status: int, successful: bool, reason: string}
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'metadata' => $this->metadata,
            'status' => $this->status,
            'successful' => $this->successful(),
            'reason' => $this->reason(),
        ];
    }

    /**
     * @return list<string>
     */
    private function outputList(string $key): array
    {
        $output = $this->metadata['output'] ?? null;
        $items = is_array($output) ? ($output[$key] ?? []) : [];

        if (is_string($items)) {
            return $items === '' ? [] : [$items];
        }

        return is_array($items) ? array_values(array_map(strval(...), array_filter($items, is_scalar(...)))) : [];
    }
}
