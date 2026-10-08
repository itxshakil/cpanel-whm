<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature;

use Carbon\CarbonImmutable;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Health\WhmCheck;
use Itxshakil\CpanelWhm\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Health\Enums\Status;

final class HealthCheckTest extends TestCase
{
    #[Test]
    public function a_working_connection_is_ok(): void
    {
        Whm::fake(['version' => Whm::response(['version' => '11.138.0.10'])]);

        $result = WhmCheck::new()->run();

        self::assertSame(Status::ok()->value, $result->status->value);
        self::assertStringStartsWith('11.138.0.10', $result->getShortSummary());
        self::assertSame('main', $result->meta['connection']);
        self::assertSame('WHM', WhmCheck::new()->getName());
        self::assertSame('WHM (ca-1)', WhmCheck::new()->connection('ca-1')->getName());
    }

    #[Test]
    public function an_unreachable_server_fails_with_the_hint(): void
    {
        Whm::fake(['version' => Whm::httpError(401)]);

        $result = WhmCheck::new()->connection('ca-1')->run();

        self::assertSame(Status::failed()->value, $result->status->value);
        self::assertStringContainsString('Manage API Tokens', $result->notificationMessage);
        self::assertSame('ca-1', $result->meta['connection']);
    }

    #[Test]
    public function the_summary_names_what_failed(): void
    {
        $cases = [
            'Token rejected' => Whm::httpError(401),
            'Missing privilege' => Whm::failure('Access denied'),
            'Unreachable' => Whm::connectionError(),
            'HTTP 502' => Whm::httpError(502),
            'WHM error' => Whm::failure('Something else went wrong.'),
        ];

        foreach ($cases as $summary => $response) {
            Whm::fake(['version' => $response]);

            $result = WhmCheck::new()->run();

            self::assertSame(Status::failed()->value, $result->status->value, $summary);
            self::assertSame($summary, $result->getShortSummary());
        }
    }

    #[Test]
    public function the_check_makes_one_attempt_so_its_timing_is_one_request(): void
    {
        config()->set('cpanel-whm.connections.main.retry', ['times' => 2, 'sleep_ms' => 0]);
        $fake = Whm::fake(['version' => Whm::connectionError()]);

        WhmCheck::new()->run();

        $fake->assertCalledTimes('version', 1);
    }

    #[Test]
    public function a_slow_server_warns(): void
    {
        Whm::fake(['version' => static function () {
            usleep(30_000);

            return Whm::response(['version' => '11.138.0.10']);
        }]);

        $result = WhmCheck::new()->warnWhenSlowerThan(1)->run();

        self::assertSame(Status::warning()->value, $result->status->value);
    }

    #[Test]
    public function an_expiring_token_fails(): void
    {
        CarbonImmutable::setTestNow('2026-10-03');
        Whm::fake([
            'version' => Whm::response(['version' => '11.138.0.10']),
            'api_token_list' => Whm::response(['tokens' => ['app' => ['expires_at' => CarbonImmutable::parse('2026-10-05')->getTimestamp()]]]),
        ]);

        $result = WhmCheck::new()->failWhenTokenExpiresWithin(7)->run();

        self::assertSame(Status::failed()->value, $result->status->value);
        self::assertStringContainsString('app', $result->notificationMessage);
        CarbonImmutable::setTestNow();
    }
}
