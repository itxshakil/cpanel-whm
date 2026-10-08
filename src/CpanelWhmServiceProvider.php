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
use Itxshakil\CpanelWhm\Contracts\WhmClient as WhmClientContract;
use Itxshakil\CpanelWhm\Doctor\NetworkProbe;
use Itxshakil\CpanelWhm\Doctor\SocketProbe;
use Itxshakil\CpanelWhm\Exceptions\InvalidConfiguration;
use Itxshakil\CpanelWhm\Support\RequestLogger;

final class CpanelWhmServiceProvider extends ServiceProvider
{
    /**
     * Named as a string so Octane stays optional.
     */
    private const string OCTANE_REQUEST_RECEIVED = 'Laravel\\Octane\\Events\\RequestReceived';

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
     * Under Octane the manager outlives a request. Each request gets clients
     * built from that request's sandbox (its config, events and cache).
     */
    private function registerOctaneReset(): void
    {
        $this->app->make(Dispatcher::class)->listen(self::OCTANE_REQUEST_RECEIVED, function (object $event): void {
            $sandbox = $event->sandbox ?? null;

            if ($sandbox instanceof Container && $this->app->resolved(WhmManager::class)) {
                $this->app->make(WhmManager::class)->setContainer($sandbox);
            }
        });
    }

    private function registerLogging(): void
    {
        $channel = $this->app->make(Repository::class)->get('cpanel-whm.log_channel');

        if (! is_string($channel) || $channel === '') {
            return;
        }

        (new RequestLogger($this->app->make(LogManager::class), $channel))->subscribe($this->app->make(Dispatcher::class));
    }
}
