<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

/**
 * A MySQL database, from UAPI Mysql::list_databases.
 */
final readonly class MysqlDatabase
{
    /**
     * @param  list<string>  $users  database users with privileges on it
     */
    public function __construct(
        public string $name,
        public int $diskUsageBytes,
        public array $users,
    ) {}

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            name: Value::string($row['database'] ?? null) ?? '',
            diskUsageBytes: Value::int($row['disk_usage'] ?? null) ?? 0,
            users: Value::strings($row['users'] ?? null),
        );
    }
}
