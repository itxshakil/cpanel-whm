<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use Illuminate\Support\Collection;
use Itxshakil\CpanelWhm\Data\Package;
use Itxshakil\CpanelWhm\Exceptions\WhmException;

/**
 * Hosting packages (plans): listpkgs.
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
}
