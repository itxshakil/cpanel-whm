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
        $channel->shouldReceive('log')->once()->withArgs(static fn (string $level, string $message, array $context): bool => $level === 'info'
            && $message === 'WHM passwd: OK'
            && $context['params'] === ['user' => 'acme', 'password' => '[REDACTED]', 'db_pass_update' => 0]
            && ! array_key_exists('response', $context));
        $channel->shouldReceive('log')->once()->withArgs(static fn (string $level, string $message): bool => $level === 'warning' && str_contains($message, 'WHM version failed'));
        Log::shouldReceive('channel')->with('whm')->andReturn($channel);

        (new RequestLogger($this->app->make('log'), 'whm'))->subscribe($this->app->make('events'));

        Whm::fake(['passwd' => Whm::response(), 'version' => Whm::failure('nope')]);

        Whm::accounts()->changePassword('acme', 'hunter2');
        rescue(static fn () => Whm::server()->version(), report: false);
    }

    #[Test]
    public function responses_can_be_logged_redacted_with_levels_by_outcome(): void
    {
        $logged = [];
        $channel = Mockery::mock();
        $channel->shouldReceive('log')->times(3)->andReturnUsing(static function (string $level, string $message, array $context) use (&$logged): void {
            $logged[] = [$level, $context['response'] ?? null];
        });
        Log::shouldReceive('channel')->with('whm')->andReturn($channel);

        (new RequestLogger($this->app->make('log'), 'whm', logResponses: true, levels: ['success' => 'debug', 'command_failed' => 'warning', 'error' => 'error']))
            ->subscribe($this->app->make('events'));

        Whm::fake([
            'api_token_create' => Whm::response(['token' => 'NEW-TOKEN']),
            'suspendacct' => Whm::failure('nope', ['token' => 'LEAKED']),
            'version' => Whm::httpError(500),
        ]);

        Whm::call('api_token_create', ['token_name' => 'ci']);
        rescue(static fn () => Whm::suspensions()->suspend('acme'), report: false);
        rescue(static fn () => Whm::withoutRetries()->server()->version(), report: false);

        self::assertSame(['debug', 'warning', 'error'], array_column($logged, 0));
        self::assertSame('[REDACTED]', $logged[0][1]['data']['token'] ?? null);
        self::assertSame('[REDACTED]', $logged[1][1]['data']['token'] ?? null);
        self::assertNull($logged[2][1]);
        self::assertStringNotContainsString('NEW-TOKEN', json_encode($logged, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('LEAKED', json_encode($logged, JSON_THROW_ON_ERROR));
    }
}
