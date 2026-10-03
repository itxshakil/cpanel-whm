<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

/**
 * One account restoration in WHM's restore queue.
 */
final readonly class RestoreTask
{
    /**
     * @param  array<string, bool>  $options  mysql, mail_config, subdomains, give_ip
     */
    public function __construct(
        public string $user,
        public ?string $restorePoint,
        public array $options,
    ) {}

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        $options = [];

        foreach (is_array($row['options'] ?? null) ? $row['options'] : [] as $key => $value) {
            $options[(string) $key] = Value::bool($value);
        }

        $point = Value::string($row['restore_point'] ?? null);

        return new self(
            user: Value::string($row['user'] ?? null) ?? '',
            restorePoint: $point === null ? null : substr($point, 0, 10),
            options: $options,
        );
    }
}
