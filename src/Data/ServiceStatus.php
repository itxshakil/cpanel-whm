<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

/**
 * One service (httpd, exim, mysql, ...) from servicestatus.
 */
final readonly class ServiceStatus
{
    public function __construct(
        public string $name,
        public string $displayName,
        public bool $installed,
        public bool $enabled,
        public bool $running,
        public bool $monitored,
    ) {}

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        $name = Value::string($row['name'] ?? null) ?? '';

        return new self(
            name: $name,
            displayName: Value::string($row['display_name'] ?? null) ?? $name,
            installed: Value::bool($row['installed'] ?? false),
            enabled: Value::bool($row['enabled'] ?? false),
            running: Value::bool($row['running'] ?? false),
            monitored: Value::bool($row['monitored'] ?? false),
        );
    }

    /**
     * Enabled but not running: the state worth alerting on.
     */
    public function isDown(): bool
    {
        return $this->enabled && ! $this->running;
    }
}
