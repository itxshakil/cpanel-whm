<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Live;

use Itxshakil\CpanelWhm\CpanelWhmServiceProvider;
use Itxshakil\CpanelWhm\Facades\Whm;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Talks to a real WHM server. Every test is skipped unless WHM_LIVE_HOST and
 * WHM_LIVE_TOKEN are set, so composer test never leaves the machine.
 *
 *     WHM_LIVE_HOST=server.example.com WHM_LIVE_TOKEN=... composer test:live
 *
 * Optional: WHM_LIVE_USER (default root), WHM_LIVE_VERIFY_TLS=0, WHM_LIVE_ACCOUNT
 * (an existing account for UAPI checks), and WHM_LIVE_WRITE=1 with
 * WHM_LIVE_DOMAIN to create and remove a throwaway account.
 */
abstract class LiveTestCase extends Orchestra
{
    protected function setUp(): void
    {
        if (self::env('WHM_LIVE_HOST') === null || self::env('WHM_LIVE_TOKEN') === null) {
            self::markTestSkipped('Set WHM_LIVE_HOST and WHM_LIVE_TOKEN to run the live suite.');
        }

        parent::setUp();

        config()->set('cpanel-whm.connections.main', [
            'host' => self::env('WHM_LIVE_HOST'),
            'user' => self::env('WHM_LIVE_USER') ?? 'root',
            'token' => self::env('WHM_LIVE_TOKEN'),
            'verify_tls' => self::env('WHM_LIVE_VERIFY_TLS') !== '0',
            'timeout' => 60,
        ]);
    }

    protected static function env(string $name): ?string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    protected static function requireEnv(string $name, string $why): string
    {
        return self::env($name) ?? self::markTestSkipped("Set {$name} to {$why}.");
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
}
