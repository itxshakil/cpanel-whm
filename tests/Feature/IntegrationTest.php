<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Support\RequestLogger;
use Itxshakil\CpanelWhm\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

final class IntegrationTest extends TestCase
{
    #[Test]
    public function about_has_a_cpanel_whm_section(): void
    {
        Artisan::call('about', ['--only' => 'cpanel_whm']);

        $output = Artisan::output();

        self::assertStringContainsString('cPanel WHM', $output);
        self::assertStringContainsString('https://server.example.com:2087', $output);
        self::assertStringContainsString('VERIFIED', $output);
    }

    #[Test]
    public function about_says_when_the_connection_is_not_configured(): void
    {
        config()->set('cpanel-whm.connections.main.host', null);

        Artisan::call('about', ['--only' => 'cpanel_whm']);

        self::assertStringContainsString('NOT CONFIGURED', Artisan::output());
    }

    #[Test]
    public function calls_are_logged_with_redacted_parameters_when_a_channel_is_set(): void
    {
        $channel = Mockery::mock();
        $channel->shouldReceive('info')->once()->withArgs(static fn (string $message, array $context): bool => $message === 'WHM passwd: OK'
            && $context['params'] === ['user' => 'acme', 'password' => '[REDACTED]', 'db_pass_update' => 0]);
        $channel->shouldReceive('warning')->once()->withArgs(static fn (string $message): bool => str_contains($message, 'WHM version failed'));
        Log::shouldReceive('channel')->with('whm')->andReturn($channel);

        (new RequestLogger($this->app->make('log'), 'whm'))->subscribe($this->app->make('events'));

        Whm::fake(['passwd' => Whm::response(), 'version' => Whm::failure('nope')]);

        Whm::accounts()->changePassword('acme', 'hunter2');
        rescue(static fn () => Whm::server()->version(), report: false);
    }
}
