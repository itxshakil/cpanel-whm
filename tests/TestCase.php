<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests;

use Illuminate\Support\Facades\Http;
use Itxshakil\CpanelWhm\CpanelWhmServiceProvider;
use Itxshakil\CpanelWhm\Doctor\NetworkProbe;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Tests\Fixtures\FakeProbe;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // No test may reach a real server.
        Http::preventStrayRequests();
        $this->instance(NetworkProbe::class, new FakeProbe);
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app)
    {
        return [CpanelWhmServiceProvider::class];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app)
    {
        return ['Whm' => Whm::class];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        $app['config']->set('cpanel-whm.connections.main', [
            'host' => 'server.example.com',
            'user' => 'root',
            'token' => 'SECRET-TOKEN-123',
            'verify_tls' => true,
            'timeout' => 30,
            'connect_timeout' => 10,
            // Tests opt in to retries; RetryAndCacheTest covers them.
            'retry' => ['times' => 0, 'sleep_ms' => 0],
        ]);
        $app['config']->set('cpanel-whm.connections.ca-1', [
            'host' => 'https://ca1.example.com:2087',
            'user' => 'reseller',
            'token' => 'OTHER-TOKEN',
            'retry' => ['times' => 0, 'sleep_ms' => 0],
        ]);
        $app['config']->set('cache.default', 'array');
    }
}
