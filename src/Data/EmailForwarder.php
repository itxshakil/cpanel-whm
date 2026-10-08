<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

/**
 * Mail to $address is forwarded to $destination, from UAPI Email::list_forwarders.
 */
final readonly class EmailForwarder
{
    public function __construct(
        public string $address,
        public string $destination,
    ) {}

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            address: Value::string($row['forward'] ?? null) ?? '',
            destination: Value::string($row['dest'] ?? null) ?? '',
        );
    }
}
