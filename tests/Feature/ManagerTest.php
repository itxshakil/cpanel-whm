<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Itxshakil\CpanelWhm\Contracts\WhmClient;
use Itxshakil\CpanelWhm\Exceptions\InvalidConfiguration;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Tests\Fixtures\Responses;
use Itxshakil\CpanelWhm\Tests\TestCase;
use Itxshakil\CpanelWhm\WhmManager;
use PHPUnit\Framework\Attributes\Test;

final class ManagerTest extends TestCase
{
    #[Test]
    public function named_connections_use_their_own_credentials(): void
    {
        Http::fake(['*' => Http::response(Responses::envelope(['version' => '11.134.0.5']))]);

        Whm::connection('ca-1')->server()->version();

        Http::assertSent(static fn (Request $request): bool => str_starts_with($request->url(), 'https://ca1.example.com:2087/')
            && $request->header('Authorization') === ['whm reseller:OTHER-TOKEN']);
    }

    #[Test]
    public function one_client_is_reused_per_connection(): void
    {
        $manager = $this->app->make(WhmManager::class);

        self::assertSame($manager->connection('main'), $manager->connection());
        self::assertSame(['main', 'ca-1'], $manager->connectionNames());
        self::assertSame('main', Whm::getDefaultConnection());
    }

    #[Test]
    public function the_contract_resolves_to_the_default_connection(): void
    {
        self::assertSame('main', $this->app->make(WhmClient::class)->config()->name);
    }

    #[Test]
    public function an_unknown_connection_is_explained(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('WHM connection [nope] is not configured.');

        Whm::connection('nope');
    }

    #[Test]
    public function the_fake_works_without_any_whm_config(): void
    {
        config()->set('cpanel-whm.connections', []);
        $fake = Whm::fake(['version' => Whm::response(['version' => '11.134.0.5'])]);

        self::assertSame('11.134.0.5', Whm::server()->version());
        self::assertSame('whm.test', Whm::config()->host);

        $fake->assertCalled('version');
    }
}
