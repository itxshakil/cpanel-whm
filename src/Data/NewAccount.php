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
     * @param  string|null  $password  when omitted (here and in $extra), Accounts::create() generates a strong one and returns it
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
     * The password to send: the one given here or in $extra, or null.
     */
    public function givenPassword(): ?string
    {
        $fromExtra = $this->extra['password'] ?? null;

        return $this->password ?? (is_string($fromExtra) && $fromExtra !== '' ? $fromExtra : null);
    }

    public function withPassword(#[SensitiveParameter] string $password): self
    {
        return new self($this->username, $this->domain, $this->package, $password, $this->contactEmail, $this->dedicatedIp, $this->extra);
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

        // $extra goes first, so it can add parameters but never replace the validated ones.
        return [...$this->extra, ...array_filter($params, static fn (mixed $value): bool => $value !== null && $value !== '')];
    }
}
