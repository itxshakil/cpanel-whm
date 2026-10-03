<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Itxshakil\CpanelWhm\Exceptions\WhmAuthenticationFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmConnectionFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmHttpError;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class RetryAndCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cpanel-whm.connections.main.retry', ['times' => 2, 'sleep_ms' => 0]);
    }

    #[Test]
    public function read_only_calls_are_retried_after_connection_errors_and_5xx(): void
    {
        $fake = Whm::fake(['listaccts' => Whm::sequence(Whm::connectionError(), Whm::httpError(503), Whm::response(['acct' => []]))]);

        self::assertCount(0, Whm::accounts()->list());
        $fake->assertCalledTimes('listaccts', 3);
    }

    #[Test]
    public function retries_give_up_after_the_configured_number(): void
    {
        $fake = Whm::fake(['version' => Whm::connectionError()]);

        try {
            Whm::server()->version();
            self::fail('Expected WhmConnectionFailed');
        } catch (WhmConnectionFailed) {
            $fake->assertCalledTimes('version', 3);
        }
    }

    #[Test]
    public function changes_and_client_errors_are_never_retried(): void
    {
        $fake = Whm::fake([
            'suspendacct' => Whm::connectionError(),
            'version' => Whm::httpError(401),
            'listpkgs' => Whm::httpError(404),
        ]);

        foreach ([
            static fn () => Whm::suspensions()->suspend('acme'),
            static fn () => Whm::server()->version(),
            static fn () => Whm::packages()->list(),
        ] as $call) {
            try {
                $call();
            } catch (WhmConnectionFailed|WhmAuthenticationFailed|WhmHttpError) {
            }
        }

        $fake->assertCalledTimes('suspendacct', 1)->assertCalledTimes('version', 1)->assertCalledTimes('listpkgs', 1);
    }

    #[Test]
    public function cached_reads_hit_whm_once(): void
    {
        $fake = Whm::fake(['listaccts' => Whm::response(['acct' => [['user' => 'acme', 'domain' => 'acme.test']]])]);

        $first = Whm::cache(300)->accounts()->list();
        $second = Whm::cache(300)->accounts()->list();

        self::assertSame('acme', $second->first()?->username);
        self::assertEquals($first, $second);
        $fake->assertCalledTimes('listaccts', 1);

        Whm::accounts()->list();
        $fake->assertCalledTimes('listaccts', 2);
    }

    #[Test]
    public function cache_keys_include_the_parameters_and_connection(): void
    {
        $fake = Whm::fake(['*' => Whm::response(['acct' => []])]);

        Whm::cache(300)->accounts()->search('a');
        Whm::cache(300)->accounts()->search('b');
        Whm::connection('ca-1')->cache(300)->accounts()->search('a');

        $fake->assertCalledTimes('listaccts', 3);
        self::assertTrue(Cache::has('cpanel-whm:main:listaccts:'.hash('xxh128', serialize(['searchtype' => 'user', 'search' => 'a', 'searchmethod' => 'regex']))));
    }

    #[Test]
    public function changes_are_never_cached(): void
    {
        $fake = Whm::fake(['*' => Whm::response()]);

        Whm::cache(300)->suspensions()->unsuspend('acme');
        Whm::cache(300)->suspensions()->unsuspend('acme');

        $fake->assertCalledTimes('unsuspendacct', 2);
    }

    #[Test]
    public function failures_are_not_cached(): void
    {
        $fake = Whm::fake(['version' => Whm::sequence(Whm::httpError(401), Whm::response(['version' => '11.138.0.10']))]);

        try {
            Whm::cache(300)->server()->version();
        } catch (WhmAuthenticationFailed) {
        }

        self::assertSame('11.138.0.10', Whm::cache(300)->server()->version());
        $fake->assertCalledTimes('version', 2);
    }
}
