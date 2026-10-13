<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Itxshakil\CpanelWhm\Contracts\WhmClient;
use Itxshakil\CpanelWhm\Exceptions\InvalidConfiguration;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Tests\Fixtures\Responses;
use Itxshakil\CpanelWhm\Tests\TestCase;
use Itxshakil\CpanelWhm\WhmManager;
use Itxshakil\CpanelWhm\WhmRequest;
use PHPUnit\Framework\Attributes\Test;

final class ManagerTest extends TestCase
{
    #[Test]
    public function under_octane_each_request_uses_its_own_configuration(): void
    {
        Http::fake(['*' => Http::response(Responses::envelope(['version' => '11.134.0.5']))]);
        Whm::server()->version();

        $sandbox = clone $this->app;
        $config = new ConfigRepository($this->app->make('config')->all());
        $config->set('cpanel-whm.connections.main.host', 'new-server.example.com');
        $sandbox->instance('config', $config);

        $this->app->make('events')->dispatch('Laravel\\Octane\\Events\\RequestReceived', [(object) ['sandbox' => $sandbox]]);

        Whm::server()->version();

        Http::assertSent(static fn (Request $request): bool => str_starts_with($request->url(), 'https://new-server.example.com:2087/'));
    }

    #[Test]
    public function named_connections_use_their_own_credentials(): void
    {
        Http::fake(['*' => Http::response(Responses::envelope(['version' => '11.134.0.5']))]);

        Whm::connection('ca-1')->server()->version();

        Http::assertSent(static fn (Request $request): bool => str_starts_with($request->url(), 'https://ca1.example.com:2087/')
            && $request->header('Authorization') === ['whm reseller:OTHER-TOKEN']);
    }

    #[Test]
    public function a_connection_can_be_built_at_runtime_from_an_array(): void
    {
        Http::fake(['*' => Http::response(Responses::envelope(['version' => '11.134.0.5']))]);

        $client = Whm::build(['host' => 'db-server.example.com', 'user' => 'reseller', 'token' => 'DB-TOKEN', 'timeout' => 15], name: 'server-42');

        self::assertSame('11.134.0.5', $client->server()->version());
        self::assertSame('server-42', $client->config()->name);
        self::assertSame(15, $client->config()->timeout);
        Http::assertSent(static fn (Request $request): bool => str_starts_with($request->url(), 'https://db-server.example.com:2087/')
            && $request->header('Authorization') === ['whm reseller:DB-TOKEN']);

        // Built clients are not added to the configured connections.
        self::assertNotContains('server-42', Whm::connectionNames());
    }

    #[Test]
    public function a_built_connection_is_validated_like_a_configured_one(): void
    {
        $this->expectException(InvalidConfiguration::class);

        Whm::build(['host' => 'server.example.com:2083', 'token' => 'x']);
    }

    #[Test]
    public function the_fake_covers_built_connections(): void
    {
        $fake = Whm::fake(['version' => Whm::response(['version' => '11.138.0.10'])]);

        self::assertSame('11.138.0.10', Whm::build(['host' => 'db-server.example.com', 'token' => 'DB-TOKEN'], name: 'server-42')->server()->version());

        $fake->assertCalled('version', static fn (array $params, WhmRequest $request): bool => $request->connection === 'server-42');
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

    #[Test]
    public function the_fake_works_when_the_whm_variables_are_unset(): void
    {
        config()->set('cpanel-whm.connections.main', ['host' => null, 'token' => null]);
        Whm::fake(['version' => Whm::response(['version' => '11.134.0.5'])]);

        self::assertSame('11.134.0.5', Whm::server()->version());
    }

    #[Test]
    public function the_fake_still_rejects_a_misspelled_connection(): void
    {
        Whm::fake(['*' => Whm::response()]);

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('WHM connection [mian] is not configured.');

        Whm::connection('mian')->server()->version();
    }

    #[Test]
    public function the_fake_still_rejects_a_connection_production_would_reject(): void
    {
        config()->set('cpanel-whm.connections.webmail', ['host' => 'https://server.example.com:2096', 'token' => 'abc']);
        Whm::fake(['*' => Whm::response()]);

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('port 2096');

        Whm::connection('webmail')->server()->version();
    }
}
