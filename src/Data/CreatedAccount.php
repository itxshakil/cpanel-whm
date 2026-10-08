<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

use Itxshakil\CpanelWhm\WhmResponse;
use SensitiveParameter;

/**
 * The result of createacct.
 */
final readonly class CreatedAccount
{
    /**
     * @param  list<string>  $nameservers
     * @param  string|null  $password  the password the account was created with, generated when none was given;
     *                                 null on the copy that AccountCreated carries
     */
    public function __construct(
        public string $username,
        public ?string $domain,
        public ?string $ip,
        public ?string $package,
        public array $nameservers,
        public ?string $rawOutput,
        public WhmResponse $response,
        #[SensitiveParameter]
        public ?string $password = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'username' => $this->username,
            'domain' => $this->domain,
            'ip' => $this->ip,
            'package' => $this->package,
            'nameservers' => $this->nameservers,
            'rawOutput' => $this->rawOutput,
            'response' => $this->response,
            'password' => $this->password === null ? null : '[REDACTED]',
        ];
    }

    /**
     * A copy without the password, for events and anything else that is stored or logged.
     */
    public function withoutPassword(): self
    {
        return new self($this->username, $this->domain, $this->ip, $this->package, $this->nameservers, $this->rawOutput, $this->response);
    }

    /**
     * WHM may rename the account to avoid a collision. The username it echoes
     * back is the real one; fall back to the requested name when it echoes none.
     */
    public static function fromResponse(WhmResponse $response, string $requestedUsername, ?string $requestedDomain, #[SensitiveParameter] ?string $password = null): self
    {
        $nameservers = [];

        foreach (['nameserver', 'nameserver2', 'nameserver3', 'nameserver4'] as $key) {
            $value = Value::string($response->get($key));

            if ($value !== null) {
                $nameservers[] = $value;
            }
        }

        return new self(
            username: Value::string($response->get('user')) ?? $requestedUsername,
            domain: Value::string($response->get('domain')) ?? $requestedDomain,
            ip: Value::string($response->get('ip')),
            package: Value::string($response->get('package')),
            nameservers: $nameservers,
            rawOutput: $response->rawOutput(),
            response: $response,
            password: $password,
        );
    }
}
