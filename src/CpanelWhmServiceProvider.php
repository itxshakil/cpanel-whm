<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Log\LogManager;
use Illuminate\Support\ServiceProvider;
use Itxshakil\CpanelWhm\Console\AccountCommand;
use Itxshakil\CpanelWhm\Console\AccountsCommand;
use Itxshakil\CpanelWhm\Console\CallCommand;
use Itxshakil\CpanelWhm\Console\DnsCommand;
use Itxshakil\CpanelWhm\Console\DoctorCommand;
use Itxshakil\CpanelWhm\Console\FunctionsCommand;
use Itxshakil\CpanelWhm\Console\InstallCommand;
use Itxshakil\CpanelWhm\Console\LoginCommand;
use Itxshakil\CpanelWhm\Console\PackagesCommand;
use Itxshakil\CpanelWhm\Console\RecordCommand;
use Itxshakil\CpanelWhm\Console\SuspendCommand;
use Itxshakil\CpanelWhm\Console\TokenCommand;
use Itxshakil\CpanelWhm\Console\UnsuspendCommand;
use Itxshakil\CpanelWhm\Console\UsageCommand;
use Itxshakil\CpanelWhm\Contracts\WhmClient as WhmClientContract;
use Itxshakil\CpanelWhm\Doctor\NetworkProbe;
use Itxshakil\CpanelWhm\Doctor\SocketProbe;
use Itxshakil\CpanelWhm\Exceptions\InvalidConfiguration;
use Itxshakil\CpanelWhm\Support\RequestLogger;

final class CpanelWhmServiceProvider extends ServiceProvider
{
    /**
     * Octane's events for the start of a request, a task (Octane::concurrently())
     * and a tick, each with its own sandbox. Named as strings so Octane stays optional.
     */
    private const array OCTANE_OPERATION_STARTED = [
        'Laravel\\Octane\\Events\\RequestReceived',
        'Laravel\\Octane\\Events\\TaskReceived',
        'Laravel\\Octane\\Events\\TickReceived',
    ];

    /**
     * The matching ends. Octane flushes the sandbox right after these.
     */
    private const array OCTANE_OPERATION_ENDED = [
        'Laravel\\Octane\\Events\\RequestTerminated',
        'Laravel\\Octane\\Events\\TaskTerminated',
        'Laravel\\Octane\\Events\\TickTerminated',
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cpanel-whm.php', 'cpanel-whm');

        $this->app->singleton(WhmManager::class);
        $this->app->alias(WhmManager::class, 'cpanel-whm');

        // Not a singleton: resolved after Whm::fake(), it gets the faked client.
        $this->app->bind(WhmClientContract::class, static fn (Application $app): WhmClientContract => $app->make(WhmManager::class)->connection());

        $this->app->singleton(NetworkProbe::class, SocketProbe::class);
    }

    public function boot(): void
    {
        $this->registerLogging();
        $this->registerOctaneReset();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/cpanel-whm.php' => $this->app->configPath('cpanel-whm.php'),
        ], 'cpanel-whm-config');

        $this->commands([
            InstallCommand::class,
            DoctorCommand::class,
            CallCommand::class,
            FunctionsCommand::class,
            AccountsCommand::class,
            AccountCommand::class,
            LoginCommand::class,
            SuspendCommand::class,
            UnsuspendCommand::class,
            PackagesCommand::class,
            DnsCommand::class,
            TokenCommand::class,
            UsageCommand::class,
            RecordCommand::class,
        ]);

        AboutCommand::add('cPanel WHM', fn (): array => $this->aboutDetails());
    }

    /**
     * @return array<string, string>
     */
    private function aboutDetails(): array
    {
        $manager = $this->app->make(WhmManager::class);
        $name = $manager->getDefaultConnection();

        try {
            $config = $manager->configFor($name);
            $host = $config->baseUrl();
            $tls = $config->verifyTls ? '<fg=green;options=bold>VERIFIED</>' : '<fg=yellow;options=bold>NOT VERIFIED</>';
        } catch (InvalidConfiguration) {
            $host = '<fg=yellow;options=bold>NOT CONFIGURED</>';
            $tls = '-';
        }

        return [
            'Default connection' => $name,
            'Host' => $host,
            'TLS certificate' => $tls,
            'Connections' => (string) count($manager->connectionNames()),
        ];
    }

    /**
     * Under Octane the manager outlives a request. Each request, task and tick
     * gets clients built from its own sandbox (its config, events and cache),
     * and the manager goes back to the base application when it ends, so it
     * never keeps a flushed sandbox for the next operation.
     */
    private function registerOctaneReset(): void
    {
        $events = $this->app->make(Dispatcher::class);

        $events->listen(self::OCTANE_OPERATION_STARTED, function (object $event): void {
            $this->pointManagerAt($event->sandbox ?? null);
        });

        $events->listen(self::OCTANE_OPERATION_ENDED, function (object $event): void {
            $this->pointManagerAt($event->app ?? null);
        });
    }

    private function pointManagerAt(mixed $container): void
    {
        if ($container instanceof Container && $this->app->resolved(WhmManager::class)) {
            $this->app->make(WhmManager::class)->setContainer($container);
        }
    }

    private function registerLogging(): void
    {
        $config = $this->app->make(Repository::class);
        $channel = $config->get('cpanel-whm.log_channel');

        if (! is_string($channel) || $channel === '') {
            return;
        }

        $levels = $config->get('cpanel-whm.log_levels');

        (new RequestLogger(
            $this->app->make(LogManager::class),
            $channel,
            (bool) $config->get('cpanel-whm.log_responses', false),
            is_array($levels) ? $levels : [],
        ))->subscribe($this->app->make(Dispatcher::class));
    }
}
