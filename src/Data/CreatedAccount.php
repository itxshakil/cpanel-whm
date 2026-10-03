<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

use Itxshakil\CpanelWhm\WhmResponse;

/**
 * The result of createacct.
 */
final readonly class CreatedAccount
{
    /**
     * @param  list<string>  $nameservers
     */
    public function __construct(
        public string $username,
        public ?string $domain,
        public ?string $ip,
        public ?string $package,
        public array $nameservers,
        public ?string $rawOutput,
        public WhmResponse $response,
    ) {}

    /**
     * WHM may rename the account to avoid a collision. The username it echoes
     * back is the real one; fall back to the requested name when it echoes none.
     */
    public static function fromResponse(WhmResponse $response, string $requestedUsername, ?string $requestedDomain): self
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
        );
    }
}
