<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use Illuminate\Support\Collection;
use Itxshakil\CpanelWhm\Data\Subdomain;
use Itxshakil\CpanelWhm\Data\UapiResult;
use Itxshakil\CpanelWhm\Exceptions\UapiCallFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmException;

/**
 * Subdomains of one account, through UAPI SubDomain:
 * Whm::asUser('acme')->subdomains()->add('shop', 'acme.example').
 */
class UserSubdomains extends UserModule
{
    /**
     * @return Collection<int, Subdomain>
     *
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function list(): Collection
    {
        return collect(self::rows($this->user->api()->subDomain()->listsubdomains()->data))
            ->map(Subdomain::fromArray(...))
            ->values();
    }

    /**
     * Create shop.example.com from add('shop', 'example.com').
     *
     * @param  string|null  $documentRoot  relative to the home directory, e.g. public_html/shop; cPanel picks one when null
     *
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function add(string $label, string $rootDomain, ?string $documentRoot = null): UapiResult
    {
        return $this->user->api()->subDomain()->addsubdomain(domain: $label, rootdomain: $rootDomain, dir: $documentRoot);
    }

    /**
     * Delete a subdomain by its full name, e.g. shop.example.com. cPanel refuses
     * while an addon domain is parked on it, and names those in the error.
     *
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function delete(string $domain): UapiResult
    {
        return $this->user->api()->subDomain()->delsubdomain(domain: $domain);
    }
}
