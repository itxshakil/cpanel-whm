<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature\Console;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class NewCommandsTest extends TestCase
{
    #[Test]
    public function suspend_asks_first_and_unsuspend_does_not(): void
    {
        $fake = Whm::fake(['*' => Whm::response()]);

        $this->artisan('whm:suspend', ['user' => 'acme', '--reason' => 'Unpaid', '--lock' => true])
            ->expectsConfirmation('Suspend acme? The sites and mail stop working.', 'no')
            ->assertFailed();
        $fake->assertNothingSent();

        $this->artisan('whm:suspend', ['user' => 'acme', '--reason' => 'Unpaid', '--lock' => true, '--force' => true])
            ->expectsOutputToContain('acme is suspended.')
            ->assertSuccessful();

        $this->artisan('whm:unsuspend', ['user' => 'acme'])
            ->expectsOutputToContain('acme is active again.')
            ->assertSuccessful();

        $fake->assertCalled('suspendacct', static fn (array $p): bool => $p === ['user' => 'acme', 'reason' => 'Unpaid', 'disallowun' => 1]);
    }

    #[Test]
    public function suspend_reports_whm_errors(): void
    {
        Whm::fake(['unsuspendacct' => Whm::failure('User does not exist')]);

        $this->artisan('whm:unsuspend', ['user' => 'ghost'])
            ->expectsOutputToContain('User does not exist')
            ->assertFailed();
    }

    #[Test]
    public function packages_are_shown_as_a_table_or_json(): void
    {
        Whm::fake(['listpkgs' => Whm::response(['pkg' => [['name' => 'starter', 'QUOTA' => '10240', 'MAXPOP' => '10']]])]);

        $this->artisan('whm:packages')->expectsOutputToContain('starter')->assertSuccessful();
        $this->artisan('whm:packages', ['--json' => true])->expectsOutputToContain('"QUOTA": "10240"')->assertSuccessful();
    }

    #[Test]
    public function dns_lists_zones_and_records(): void
    {
        Whm::fake([
            'listzones' => Whm::response(['zone' => [['domain' => 'acme.test']]]),
            'parse_dns_zone' => Whm::response(['payload' => [
                ['type' => 'record', 'line_index' => 9, 'record_type' => 'A', 'ttl' => 14400, 'dname_b64' => base64_encode('www'), 'data_b64' => [base64_encode('203.0.113.10')]],
                ['type' => 'record', 'line_index' => 10, 'record_type' => 'TXT', 'ttl' => 14400, 'dname_b64' => base64_encode('@'), 'data_b64' => [base64_encode('hello')]],
            ]]),
        ]);

        $this->artisan('whm:dns')->expectsOutputToContain('acme.test')->assertSuccessful();
        $this->artisan('whm:dns', ['domain' => 'acme.test', '--type' => 'A'])
            ->expectsOutputToContain('203.0.113.10')
            ->doesntExpectOutputToContain('hello')
            ->expectsOutputToContain('1 record(s)')
            ->assertSuccessful();
    }

    #[Test]
    public function token_warns_and_fails_when_a_token_expires_soon(): void
    {
        CarbonImmutable::setTestNow('2026-10-03 12:00:00');
        Whm::fake(['api_token_list' => Whm::sequence(
            Whm::response(['tokens' => ['app' => ['create_time' => 1767225600, 'expires_at' => CarbonImmutable::parse('2026-10-08')->getTimestamp(), 'acls' => ['all' => 1]]]]),
            Whm::response(['tokens' => ['app' => ['create_time' => 1767225600, 'expires_at' => null, 'acls' => ['all' => 1]]]]),
            Whm::response(['tokens' => []]),
        )]);

        $this->artisan('whm:token')->expectsOutputToContain('expire within 14 days')->assertFailed();
        $this->artisan('whm:token')->expectsOutputToContain('never')->assertSuccessful();
        $this->artisan('whm:token')->expectsOutputToContain('No API tokens.')->assertSuccessful();

        CarbonImmutable::setTestNow();
    }

    #[Test]
    public function usage_lists_accounts_near_their_limits_and_fails(): void
    {
        Whm::fake([
            'get_disk_usage' => Whm::response(['accounts' => [
                ['user' => 'acme', 'blocks_used' => 950_000, 'blocks_limit' => 1_000_000],
                ['user' => 'calm', 'blocks_used' => 100, 'blocks_limit' => 1_000_000],
            ]]),
            'showbw' => Whm::response(['acct' => [['user' => 'acme', 'maindomain' => 'acme.test', 'totalbytes' => 10, 'limit' => 0]]]),
        ]);

        self::assertSame(1, Artisan::call('whm:usage', ['--threshold' => 90]));
        $output = Artisan::output();

        self::assertMatchesRegularExpression('/\| acme +\| acme\.test +\| 0\.9 GB \/ 1\.0 GB +\| 95% +\| 10 B \/ unlimited/', $output);
        self::assertStringNotContainsString('calm', $output);
        self::assertStringContainsString('1 account(s) at or above 90% of their disk or bandwidth limit.', $output);
    }

    #[Test]
    public function usage_passes_when_every_account_is_below_the_threshold(): void
    {
        Whm::fake([
            'get_disk_usage' => Whm::response(['accounts' => [['user' => 'calm', 'blocks_used' => 100, 'blocks_limit' => 1_000_000]]]),
            'showbw' => Whm::response(['acct' => []]),
        ]);

        $this->artisan('whm:usage')
            ->expectsOutputToContain('No account is at or above 90%')
            ->assertSuccessful();

        $this->artisan('whm:usage', ['--all' => true])
            ->expectsOutputToContain('calm')
            ->assertSuccessful();
    }

    #[Test]
    public function usage_prints_json(): void
    {
        Whm::fake([
            'get_disk_usage' => Whm::response(['accounts' => [['user' => 'acme', 'blocks_used' => 950, 'blocks_limit' => 1000]]]),
            'showbw' => Whm::response(['acct' => []]),
        ]);

        self::assertSame(1, Artisan::call('whm:usage', ['--json' => true]));

        self::assertSame([[
            'user' => 'acme',
            'domain' => null,
            'disk_bytes' => 972_800,
            'disk_limit_bytes' => 1_024_000,
            'disk_percent' => 95,
            'bandwidth_bytes' => null,
            'bandwidth_limit_bytes' => null,
            'bandwidth_percent' => null,
        ]], json_decode(Artisan::output(), true));
    }

    #[Test]
    public function record_saves_a_redacted_fixture_that_fake_can_replay(): void
    {
        $directory = 'build/test-fixtures-'.bin2hex(random_bytes(3));
        Whm::fake(['api_token_list' => Whm::response(['tokens' => ['app' => ['token' => 'LEAKED?', 'name' => 'app']]])]);

        $this->artisan('whm:record', ['function' => 'api_token_list', '--path' => $directory])
            ->expectsOutputToContain('Saved')
            ->assertSuccessful();

        $path = base_path($directory.'/api_token_list.json');
        self::assertFileExists($path);
        self::assertStringNotContainsString('LEAKED?', (string) file_get_contents($path));

        Whm::fake(['api_token_list' => Whm::fixture($path)]);
        self::assertSame('app', Whm::tokens()->list()->first()?->name);

        File::deleteDirectory(base_path($directory));
    }

    #[Test]
    public function record_refuses_functions_that_change_things(): void
    {
        $fake = Whm::fake();

        $this->artisan('whm:record', ['function' => 'removeacct', 'params' => ['user=acme']])
            ->expectsOutputToContain('only calls read-only functions')
            ->assertFailed();

        $fake->assertNothingSent();
    }

    #[Test]
    public function record_refuses_functions_it_does_not_know(): void
    {
        $fake = Whm::fake();

        $this->artisan('whm:record', ['function' => 'some_plugin_function'])
            ->expectsOutputToContain('not a documented WHM API 1 function')
            ->assertFailed();

        $fake->assertNothingSent();
    }

    #[Test]
    public function record_keeps_failures_too(): void
    {
        $directory = 'build/test-fixtures-'.bin2hex(random_bytes(3));
        Whm::fake(['accountsummary' => Whm::failure('Account does not exist.')]);

        $this->artisan('whm:record', ['function' => 'accountsummary', 'params' => ['user=ghost'], '--path' => $directory, '--name' => 'missing'])
            ->assertSuccessful();

        self::assertStringContainsString('Account does not exist.', (string) file_get_contents(base_path($directory.'/missing.json')));
        File::deleteDirectory(base_path($directory));
    }

    #[Test]
    public function functions_lists_generated_and_uapi_methods(): void
    {
        $this->artisan('whm:functions', ['search' => 'parse_dns_zone'])
            ->expectsOutputToContain('Whm::dns()->zone()')
            ->assertSuccessful();

        $this->artisan('whm:functions', ['search' => 'add_zone_key'])
            ->expectsOutputToContain('Whm::api()->dns()->addZoneKey()')
            ->assertSuccessful();

        $this->artisan('whm:functions', ['search' => 'list_pops', '--uapi' => true])
            ->expectsOutputToContain('asUser($user)->api()->email()->listPops()')
            ->assertSuccessful();

        $this->artisan('whm:functions', ['--curated' => true])
            ->doesntExpectOutputToContain('addZoneKey')
            ->assertSuccessful();
    }
}
