<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use DateTimeInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Itxshakil\CpanelWhm\Data\ApiToken;
use Itxshakil\CpanelWhm\Data\CreatedToken;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\WhmResponse;

/**
 * WHM API tokens of the connection's user: api_token_create, api_token_list,
 * api_token_revoke.
 *
 *     $created = Whm::tokens()->create('billing', ['create-acct', 'suspend-acct'], now()->addYear());
 *     $created->token;                               // store it: WHM shows it once
 *     Whm::tokens()->expiringWithin(14);             // what php artisan whm:token warns about
 */
class Tokens extends Module
{
    /**
     * @return Collection<int, ApiToken>
     *
     * @throws WhmException
     */
    public function list(): Collection
    {
        $tokens = $this->client->call('api_token_list')->get('tokens');

        $list = [];

        foreach (is_array($tokens) ? $tokens : [] as $name => $row) {
            if (is_array($row)) {
                $list[] = ApiToken::fromArray((string) $name, $row);
            }
        }

        return collect($list)->sortBy('name')->values();
    }

    /**
     * @throws WhmException
     */
    public function find(string $name): ?ApiToken
    {
        return $this->list()->first(static fn (ApiToken $token): bool => $token->name === $name);
    }

    /**
     * Tokens that are still valid but expire within $days.
     *
     * Already expired tokens are left out: WHM keeps them in the list until
     * someone revokes them, and one can't be the token in use, since that
     * token just listed them. Counting them would fail a check forever.
     *
     * @return Collection<int, ApiToken>
     *
     * @throws WhmException
     */
    public function expiringWithin(int $days): Collection
    {
        return $this->list()->filter(static fn (ApiToken $token): bool => ! $token->isExpired() && $token->expiresWithin($days))->values();
    }

    /**
     * Create a token for the connection's user.
     *
     * @param  list<string>|null  $privileges  ACL names; null gives the token all of the user's privileges
     * @param  list<string>|null  $allowedIps  IPs or CIDR ranges; null allows any address
     *
     * @throws InvalidArgumentException when $privileges or $allowedIps is an empty list
     * @throws WhmException
     */
    public function create(string $name, ?array $privileges = null, ?DateTimeInterface $expiresAt = null, ?array $allowedIps = null): CreatedToken
    {
        if (preg_match('/^[A-Za-z0-9_-]{1,50}$/', $name) !== 1) {
            throw new InvalidArgumentException('A WHM API token name is 1 to 50 letters, digits, dashes and underscores.');
        }

        // WHM reads "no acl" as every privilege and "no whitelist_ip" as any address,
        // so an empty list (say, a form where nothing was ticked) would create the
        // most powerful token there is. Only an explicit null may mean that.
        if ($privileges === []) {
            throw new InvalidArgumentException('An empty privilege list would give the token every privilege. Pass null to mean that, or list at least one.');
        }

        if ($allowedIps === []) {
            throw new InvalidArgumentException('An empty IP list would let the token be used from any address. Pass null to mean that, or list at least one.');
        }

        $params = ['token_name' => $name, 'expires_at' => $expiresAt?->getTimestamp()];

        foreach ($privileges ?? [] as $index => $privilege) {
            $params['acl-'.$index] = $privilege;
        }

        foreach ($allowedIps ?? [] as $index => $ip) {
            $params['whitelist_ip-'.$index] = $ip;
        }

        $response = $this->client->call('api_token_create', $params, HttpMethod::Post);

        return CreatedToken::fromArray($name, $response->data);
    }

    /**
     * Revoke one or more tokens by name.
     *
     * @throws WhmException
     */
    public function revoke(string ...$names): WhmResponse
    {
        $params = [];

        foreach (array_values($names) as $index => $name) {
            $params[$index === 0 ? 'token_name' : 'token_name-'.$index] = $name;
        }

        return $this->client->call('api_token_revoke', $params, HttpMethod::Post);
    }
}
