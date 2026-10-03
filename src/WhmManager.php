<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Itxshakil\CpanelWhm\Contracts\Transport;
use Itxshakil\CpanelWhm\Contracts\WhmClient as WhmClientContract;
use Itxshakil\CpanelWhm\Doctor\ConnectionDoctor;
use Itxshakil\CpanelWhm\Doctor\ConnectionReport;
use Itxshakil\CpanelWhm\Exceptions\InvalidConfiguration;
use Itxshakil\CpanelWhm\Support\ConnectionConfig;
use Itxshakil\CpanelWhm\Testing\WhmFake;
use Itxshakil\CpanelWhm\Transport\HttpTransport;

/**
 * Resolves named connections, like DatabaseManager. Calls it does not know
 * are forwarded to the default connection, so Whm::accounts() works.
 *
 * @mixin WhmClient
 */
class WhmManager
{
    /**
     * @var array<string, WhmClient>
     */
    private array $clients = [];

    private ?WhmFake $fake = null;

    public function __construct(private readonly Container $container) {}

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->connection()->{$method}(...$arguments);
    }

    /**
     * @throws InvalidConfiguration
     */
    public function connection(?string $name = null): WhmClientContract
    {
        $name ??= $this->getDefaultConnection();

        return $this->clients[$name] ??= new WhmClient(
            $this->configFor($name),
            $this->transport(),
            $this->container->make(Dispatcher::class),
        );
    }

    /**
     * @throws InvalidConfiguration
     */
    public function configFor(string $name): ConnectionConfig
    {
        $config = $this->config()->get("cpanel-whm.connections.{$name}");

        if (! is_array($config)) {
            if ($this->fake instanceof WhmFake) {
                return ConnectionConfig::fake($name);
            }

            throw InvalidConfiguration::unknownConnection($name);
        }

        /** @var array<string, mixed> $config */
        try {
            return ConnectionConfig::fromArray($name, $config);
        } catch (InvalidConfiguration $invalidConfiguration) {
            if ($this->fake instanceof WhmFake) {
                return ConnectionConfig::fake($name);
            }

            throw $invalidConfiguration;
        }
    }

    public function getDefaultConnection(): string
    {
        $default = $this->config()->get('cpanel-whm.default');

        return is_string($default) && $default !== '' ? $default : 'main';
    }

    /**
     * @return list<string>
     */
    public function connectionNames(): array
    {
        $connections = $this->config()->get('cpanel-whm.connections');

        return is_array($connections) ? array_map(strval(...), array_keys($connections)) : [];
    }

    /**
     * Run the whm:doctor checks against a connection and return the report.
     */
    public function ping(?string $connection = null): ConnectionReport
    {
        return $this->container->make(ConnectionDoctor::class)->diagnose($this, $connection ?? $this->getDefaultConnection());
    }

    /**
     * Replace every connection's transport with a fake. Pass responses keyed by
     * function name; see the testing docs.
     *
     * @param  array<string, mixed>  $responses
     */
    public function fake(array $responses = []): WhmFake
    {
        $this->fake = new WhmFake($responses);
        $this->clients = [];

        return $this->fake;
    }

    public function isFaked(): bool
    {
        return $this->fake instanceof WhmFake;
    }

    public function getFake(): ?WhmFake
    {
        return $this->fake;
    }

    /**
     * Forget resolved clients, e.g. after config changes at runtime.
     */
    public function purge(?string $name = null): void
    {
        if ($name === null) {
            $this->clients = [];

            return;
        }

        unset($this->clients[$name]);
    }

    private function transport(): Transport
    {
        return $this->fake ?? $this->container->make(HttpTransport::class);
    }

    private function config(): Repository
    {
        return $this->container->make(Repository::class);
    }
}
