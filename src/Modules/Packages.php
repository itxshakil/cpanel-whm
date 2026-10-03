<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use Illuminate\Support\Collection;
use Itxshakil\CpanelWhm\Data\Package;
use Itxshakil\CpanelWhm\Data\PackageDefinition;
use Itxshakil\CpanelWhm\Data\Value;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Exceptions\WhmCommandFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\WhmResponse;

/**
 * Hosting packages (plans): listpkgs, getpkginfo, addpkg, editpkg, killpkg.
 */
class Packages extends Module
{
    /**
     * @return Collection<int, Package>
     *
     * @throws WhmException
     */
    public function list(): Collection
    {
        $response = $this->client->call('listpkgs');

        return collect($this->rows($response->get('pkg')))->map(Package::fromArray(...))->values();
    }

    /**
     * @throws WhmException
     */
    public function find(string $name): ?Package
    {
        return $this->list()->first(static fn (Package $package): bool => $package->name === $name);
    }

    /**
     * One package's full settings with getpkginfo, or null when it does not exist.
     *
     * @throws WhmException
     */
    public function info(string $name): ?Package
    {
        try {
            $settings = $this->client->call('getpkginfo', ['pkg' => $name])->get('pkg');
        } catch (WhmCommandFailed $whmCommandFailed) {
            if (str_contains(mb_strtolower($whmCommandFailed->reason()), 'does not exist')) {
                return null;
            }

            throw $whmCommandFailed;
        }

        return is_array($settings) ? Package::fromArray(['name' => $name, ...$settings]) : null;
    }

    /**
     * Create a package. Returns the name WHM saved it under (a reseller's
     * packages are prefixed with the reseller's username).
     *
     * @throws WhmException
     */
    public function create(PackageDefinition $package): string
    {
        $response = $this->client->call('addpkg', $package->toParams(), HttpMethod::Post);

        return Value::string($response->get('pkg')) ?? $package->name;
    }

    /**
     * Change a package. Only the settings that are not null are sent.
     *
     * @throws WhmException
     */
    public function update(PackageDefinition $package): WhmResponse
    {
        return $this->client->call('editpkg', $package->toParams(forUpdate: true), HttpMethod::Post);
    }

    /**
     * Delete a package. WHM refuses while an account still uses it.
     *
     * @throws WhmException
     */
    public function delete(string $name): WhmResponse
    {
        return $this->client->call('killpkg', ['pkgname' => $name], HttpMethod::Post);
    }
}
