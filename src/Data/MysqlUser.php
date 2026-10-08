<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

/**
 * A MySQL user, from UAPI Mysql::list_users.
 */
final readonly class MysqlUser
{
    /**
     * @param  string  $shortName  the name without the account prefix
     * @param  list<string>  $databases  databases the user has privileges on
     */
    public function __construct(
        public string $name,
        public string $shortName,
        public array $databases,
    ) {}

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        $name = Value::string($row['user'] ?? null) ?? '';

        return new self(
            name: $name,
            shortName: Value::string($row['shortuser'] ?? null) ?? $name,
            databases: Value::strings($row['databases'] ?? null),
        );
    }
}
