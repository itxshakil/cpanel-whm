<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use Illuminate\Support\Collection;
use Itxshakil\CpanelWhm\Data\Account;
use Itxshakil\CpanelWhm\Data\DomainInfo;
use Itxshakil\CpanelWhm\Data\Value;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Exceptions\WhmCommandFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Support\UsernameRules;
use Itxshakil\CpanelWhm\WhmResponse;

/**
 * Domains across accounts: get_domain_info, getdomainowner, domainuserdata,
 * create_subdomain, create_parked_domain_for_user, delete_domain.
 *
 * For one account's addon domains and aliases from that account's side, use
 * Whm::asUser($user)->api()->addonDomain() / park() / subDomain().
 */
class Domains extends Module
{
    /**
     * Every domain on the server, or one account's domains.
     *
     * @return Collection<int, DomainInfo>
     *
     * @throws WhmException
     */
    public function list(?string $user = null): Collection
    {
        $domains = collect($this->rows($this->client->call('get_domain_info')->get('domains')))
            ->map(DomainInfo::fromArray(...));

        if ($user !== null) {
            $user = UsernameRules::normalise($user);
            $domains = $domains->filter(static fn (DomainInfo $domain): bool => $domain->user === $user);
        }

        return $domains->values();
    }

    /**
     * @throws WhmException
     */
    public function find(string $domain): ?DomainInfo
    {
        $domain = mb_strtolower(rtrim($domain, '.'));

        return $this->list()->first(static fn (DomainInfo $info): bool => mb_strtolower($info->domain) === $domain);
    }

    /**
     * The cPanel account that owns a domain, or null when no account does.
     *
     * @throws WhmException
     */
    public function owner(string $domain): ?string
    {
        try {
            return Value::string($this->client->call('getdomainowner', ['domain' => $domain])->get('user'));
        } catch (WhmCommandFailed) {
            return null;
        }
    }

    /**
     * Apache settings for a domain (document root, IP, aliases, owner, ...).
     *
     * @return array<array-key, mixed>
     *
     * @throws WhmException
     */
    public function userData(string $domain): array
    {
        $data = $this->client->call('domainuserdata', ['domain' => $domain])->get('userdata');

        return is_array($data) ? $data : [];
    }

    /**
     * Create a subdomain ("blog.example.com") on the account that owns the
     * parent domain. Returns that account's username.
     *
     * @param  string  $documentRoot  relative to the account's home directory, e.g. "public_html/blog"
     *
     * @throws WhmException
     */
    public function createSubdomain(string $subdomain, string $documentRoot, bool $useCanonicalName = false): ?string
    {
        $response = $this->client->call('create_subdomain', [
            'domain' => $subdomain,
            'document_root' => $documentRoot,
            'use_canonical_name' => $useCanonicalName,
        ], HttpMethod::Post);

        return Value::string($response->get('username'));
    }

    /**
     * Add a domain alias (a parked domain) to an account. It serves the same
     * site as $webVhostDomain, which defaults to the account's main domain.
     *
     * @throws WhmException
     */
    public function createAlias(string $user, string $domain, ?string $webVhostDomain = null): WhmResponse
    {
        $user = UsernameRules::normalise($user);

        if ($webVhostDomain === null) {
            $account = $this->client->accounts()->find($user);
            $webVhostDomain = $account instanceof Account ? $account->domain : null;
        }

        return $this->client->call('create_parked_domain_for_user', [
            'username' => $user,
            'domain' => $domain,
            'web_vhost_domain' => $webVhostDomain,
        ], HttpMethod::Post);
    }

    /**
     * Delete an addon domain, subdomain or alias. Returns its type: addon, sub or parked.
     *
     * @throws WhmException
     */
    public function delete(string $domain): ?string
    {
        return Value::string($this->client->call('delete_domain', ['domain' => $domain], HttpMethod::Post)->get('type'));
    }
}
