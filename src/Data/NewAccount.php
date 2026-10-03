<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

use Itxshakil\CpanelWhm\Exceptions\InvalidUsername;
use Itxshakil\CpanelWhm\Support\UsernameRules;
use SensitiveParameter;

/**
 * The input for createacct.
 */
final readonly class NewAccount
{
    /**
     * @param  string|null  $password  WHM generates a strong one when omitted
     * @param  array<string, mixed>  $extra  any other createacct parameter, sent as-is
     */
    public function __construct(
        public string $username,
        public ?string $domain = null,
        public ?string $package = null,
        #[SensitiveParameter]
        public ?string $password = null,
        public ?string $contactEmail = null,
        public bool $dedicatedIp = false,
        public array $extra = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'username' => $this->username,
            'domain' => $this->domain,
            'package' => $this->package,
            'password' => $this->password === null ? null : '[REDACTED]',
            'contactEmail' => $this->contactEmail,
            'dedicatedIp' => $this->dedicatedIp,
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidUsername
     */
    public function toParams(): array
    {
        $params = [
            'username' => UsernameRules::assertValid($this->username),
            'domain' => $this->domain !== null ? mb_strtolower(trim($this->domain)) : null,
            'plan' => $this->package,
            'password' => $this->password,
            'contactemail' => $this->contactEmail,
            'ip' => $this->dedicatedIp ? 'y' : null,
        ];

        return [...array_filter($params, static fn (mixed $value): bool => $value !== null && $value !== ''), ...$this->extra];
    }
}
