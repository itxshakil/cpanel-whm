<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

/**
 * How many accounts a reseller owns, from acctcounts.
 */
final readonly class ResellerAccountCounts
{
    public function __construct(
        public string $user,
        public int $active,
        public int $suspended,
        public ?int $limit,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $limit = Value::int($data['limit'] ?? null);

        return new self(
            user: Value::string($data['user'] ?? null) ?? '',
            active: Value::int($data['active'] ?? null) ?? 0,
            suspended: Value::int($data['suspended'] ?? null) ?? 0,
            limit: $limit !== null && $limit > 0 ? $limit : null,
        );
    }

    public function remaining(): ?int
    {
        return $this->limit === null ? null : max(0, $this->limit - $this->active - $this->suspended);
    }
}
