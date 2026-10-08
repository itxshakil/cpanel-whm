<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

use SensitiveParameter;

/**
 * The result of creating a mailbox with Whm::asUser($user)->email()->create().
 */
final readonly class CreatedEmailAccount
{
    /**
     * @param  string  $password  the one given, or the strong one generated when none was
     */
    public function __construct(
        public string $address,
        #[SensitiveParameter]
        public string $password,
        public UapiResult $result,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['address' => $this->address, 'password' => '[REDACTED]', 'result' => $this->result];
    }
}
