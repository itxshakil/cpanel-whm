<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

use SensitiveParameter;

/**
 * The result of Whm::asUser($user)->mysql()->createUser().
 */
final readonly class CreatedMysqlUser
{
    /**
     * @param  string  $password  the one given, or the strong one generated when none was
     */
    public function __construct(
        public string $name,
        #[SensitiveParameter]
        public string $password,
        public UapiResult $result,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['name' => $this->name, 'password' => '[REDACTED]', 'result' => $this->result];
    }
}
