<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use Illuminate\Support\Collection;
use Itxshakil\CpanelWhm\Data\DiskPartition;
use Itxshakil\CpanelWhm\Data\LoadAverage;
use Itxshakil\CpanelWhm\Data\ServiceStatus;
use Itxshakil\CpanelWhm\Data\Value;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\WhmResponse;

/**
 * The server and the token itself: version, applist, myprivs, gethostname,
 * systemloadavg, servicestatus, restartservice, getdiskusage.
 */
class Server extends Module
{
    /**
     * The cPanel & WHM version, e.g. "11.134.0.5". Also the cheapest way to
     * check that the connection and token work.
     *
     * @throws WhmException
     */
    public function version(): string
    {
        return Value::string($this->client->call('version')->get('version')) ?? '';
    }

    /**
     * The WHM API 1 functions this server offers.
     *
     * @return list<string>
     *
     * @throws WhmException
     */
    public function functions(): array
    {
        return Value::strings($this->client->call('applist')->get('app'));
    }

    /**
     * The privileges the token's user has, e.g. ["all"] for root or
     * ["create-acct", "suspend-acct", ...] for a reseller.
     *
     * @return list<string>
     *
     * @throws WhmException
     */
    public function privileges(): array
    {
        $privileges = $this->client->call('myprivs')->get('privileges');

        if (! is_array($privileges)) {
            return [];
        }

        if (array_is_list($privileges) && isset($privileges[0]) && is_array($privileges[0])) {
            $privileges = $privileges[0];
        }

        if (array_is_list($privileges)) {
            return Value::strings($privileges);
        }

        $granted = [];

        foreach ($privileges as $name => $enabled) {
            if (Value::bool($enabled)) {
                $granted[] = (string) $name;
            }
        }

        return $granted;
    }

    /**
     * @throws WhmException
     */
    public function hasPrivilege(string $privilege): bool
    {
        $privileges = $this->privileges();

        return in_array('all', $privileges, true) || in_array($privilege, $privileges, true);
    }

    /**
     * @throws WhmException
     */
    public function hostname(): string
    {
        return Value::string($this->client->call('gethostname')->get('hostname')) ?? '';
    }

    /**
     * @throws WhmException
     */
    public function loadAverage(): LoadAverage
    {
        return LoadAverage::fromArray($this->client->call('systemloadavg')->data);
    }

    /**
     * The status of every service, or of one ("httpd", "exim", "mysql", ...).
     *
     * @return Collection<int, ServiceStatus>
     *
     * @throws WhmException
     */
    public function services(?string $service = null): Collection
    {
        $response = $this->client->call('servicestatus', ['service' => $service]);

        return collect($this->rows($response->get('service')))->map(ServiceStatus::fromArray(...))->values();
    }

    /**
     * @param  bool  $queue  restart in the background instead of waiting for it
     *
     * @throws WhmException
     */
    public function restartService(string $service, bool $queue = false): WhmResponse
    {
        return $this->client->call('restartservice', ['service' => $service, 'queue_task' => $queue], HttpMethod::Post);
    }

    /**
     * The server's mounted partitions and how full they are.
     *
     * @return Collection<int, DiskPartition>
     *
     * @throws WhmException
     */
    public function partitions(): Collection
    {
        return collect($this->rows($this->client->call('getdiskusage')->get('partition')))->map(DiskPartition::fromArray(...))->values();
    }
}
