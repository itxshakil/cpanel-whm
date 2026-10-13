<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature\Console;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Itxshakil\CpanelWhm\Doctor\CertificateInfo;
use Itxshakil\CpanelWhm\Doctor\CheckResult;
use Itxshakil\CpanelWhm\Doctor\ConnectionDoctor;
use Itxshakil\CpanelWhm\Doctor\NetworkProbe;
use Itxshakil\CpanelWhm\Enums\CheckStatus;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Tests\Fixtures\FakeProbe;
use Itxshakil\CpanelWhm\Tests\Fixtures\Responses;
use Itxshakil\CpanelWhm\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class DoctorCommandTest extends TestCase
{
    #[Test]
    public function a_healthy_connection_passes_every_check(): void
    {
        $this->healthyServer();

        $report = Whm::ping();

        self::assertTrue($report->passed());
        self::assertSame(ConnectionDoctor::ORDER, array_map(static fn (CheckResult $check): string => $check->name, $report->checks));

        foreach ($report->checks as $check) {
            self::assertSame(CheckStatus::Pass, $check->status, $check->name);
        }

        self::assertSame('cPanel & WHM 11.134.0.5', $report->check('Version')?->message);
    }

    #[Test]
    public function an_ipv6_host_needs_no_lookup_and_is_probed_without_brackets(): void
    {
        config()->set('cpanel-whm.connections.main.host', 'https://[2001:db8::1]:2087');
        Whm::purge();
        $this->healthyServer();
        $this->probe($probe = new FakeProbe);

        $report = Whm::ping();

        self::assertTrue($report->passed());
        self::assertStringContainsString('is an IP address', (string) $report->check('DNS')?->message);
        self::assertSame(['2001:db8::1', '2001:db8::1'], $probe->hosts);
    }

    #[Test]
    public function the_report_is_printed_and_the_exit_code_is_0(): void
    {
        $this->healthyServer();

        $this->artisan('whm:doctor')
            ->expectsOutputToContain('WHM connection · main · https://server.example.com:2087')
            ->expectsOutputToContain('port 2087 open (38 ms)')
            ->expectsOutputToContain('The connection works.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_is_also_available_as_whm_test(): void
    {
        $this->healthyServer();

        $this->artisan('whm:test')->assertSuccessful();
    }

    /**
     * @param  Closure(self): void  $arrange
     */
    #[Test]
    #[DataProvider('failures')]
    public function it_stops_at_the_first_failure_explains_it_and_skips_the_rest(string $failing, Closure $arrange, string $message, string $hint): void
    {
        // Http::fake() stubs registered first win, so the failing case goes first.
        $arrange($this);
        $this->healthyServer();

        $report = Whm::ping();
        $failure = $report->firstFailure();

        self::assertSame($failing, $failure?->name);
        self::assertStringContainsString($message, (string) $failure?->message);
        self::assertStringContainsString($hint, (string) $failure?->hint);
        self::assertSame(CheckStatus::Skip, $report->check('Latency')?->status);
    }

    /** @return array<string, array{string, Closure(self): void, string, string}> */
    public static function failures(): array
    {
        return [
            'missing token' => ['Config', static fn () => config()->set('cpanel-whm.connections.main.token', ''), 'has no token', 'WHM_TOKEN'],
            'cPanel port' => ['Config', static fn () => config()->set('cpanel-whm.connections.main.host', 'server.example.com:2083'), 'port 2083', '2087'],
            'DNS' => ['DNS', static fn (self $test) => $test->probe(new FakeProbe(addresses: [])), 'does not resolve', 'WHM_HOST'],
            'firewall' => ['Network', static fn (self $test) => $test->probe(new FakeProbe(connectError: 'Connection refused')), 'unreachable: Connection refused', 'firewall'],
            'bad certificate' => ['TLS', static fn (self $test) => $test->probe(new FakeProbe(certificate: new CertificateInfo(false, null, null, 'self-signed certificate'))), 'self-signed', 'WHM_VERIFY_TLS=false'],
            'TLS handshake' => ['TLS', static fn (self $test) => $test->probe(new FakeProbe(tlsError: 'wrong version number')), 'handshake failed', 'HTTPS'],
            'rejected token' => ['Auth', static fn () => Http::fake(['*' => Http::response('denied', 401)]), 'HTTP 401', 'Manage API Tokens'],
            'login page instead of WHM' => ['API', static fn () => Http::fake(['*' => Http::response('<html>Login</html>', 200)]), 'version', 'login page or proxy'],
            'server error' => ['API', static fn () => Http::fake(['*' => Http::response('', 500)]), 'HTTP 500', ''],
            'missing privilege' => ['Privileges', static function (self $test): void {
                config()->set('cpanel-whm.doctor.required_privileges', ['create-acct', 'kill-acct']);
                $test->healthyServer(privileges: ['create-acct' => 1]);
            }, 'missing: kill-acct', 'ACL'],
            'missing function' => ['Functions', static function (self $test): void {
                config()->set('cpanel-whm.doctor.required_functions', ['uapi_cpanel']);
                $test->healthyServer(functions: ['version']);
            }, 'uapi_cpanel', 'Update cPanel'],
            'unreadable privileges' => ['Privileges', static function (): void {
                config()->set('cpanel-whm.doctor.required_privileges', ['create-acct']);
                Http::fake(['*/json-api/myprivs*' => Http::response(Responses::envelope(null, result: 0, reason: 'Access denied', command: 'myprivs'))]);
            }, 'could not read privileges', 'whm:doctor'],
        ];
    }

    #[Test]
    public function a_failed_check_prints_the_hint_and_exits_1(): void
    {
        Http::fake(['*' => Http::response('denied', 401)]);

        $this->artisan('whm:doctor')
            ->expectsOutputToContain('Manage API Tokens')
            ->expectsOutputToContain('Auth check failed.')
            ->assertFailed();
    }

    #[Test]
    public function a_server_that_only_answers_on_retry_is_reported_not_hidden(): void
    {
        config()->set('cpanel-whm.connections.main.retry', ['times' => 2, 'sleep_ms' => 0]);
        Http::fake([
            '*/json-api/version*' => Http::sequence()->push('', 503)->push(Responses::envelope(['version' => '11.134.0.5'])),
            '*/json-api/myprivs*' => Http::response(Responses::envelope(['privileges' => [['all' => 1]]], command: 'myprivs')),
            '*/json-api/applist*' => Http::response(Responses::envelope(['app' => ['version']], command: 'applist')),
        ]);

        $report = Whm::ping();

        self::assertFalse($report->passed());
        self::assertStringContainsString('HTTP 503', (string) $report->firstFailure()?->message);
        Http::assertSentCount(1);
    }

    #[Test]
    public function an_untrusted_certificate_only_warns_when_verification_is_off(): void
    {
        $this->healthyServer();
        config()->set('cpanel-whm.connections.main.verify_tls', false);
        $this->probe(new FakeProbe(certificate: new CertificateInfo(false, error: 'self-signed')));

        $report = Whm::ping();

        self::assertTrue($report->passed());
        self::assertSame(CheckStatus::Warn, $report->check('TLS')?->status);
    }

    #[Test]
    public function a_self_signed_certificate_passes_when_its_key_matches_the_pin(): void
    {
        $pin = 'sha256//'.base64_encode(str_repeat('k', 32));
        $this->healthyServer();
        config()->set('cpanel-whm.connections.main.verify_tls', false);
        config()->set('cpanel-whm.connections.main.pinned_public_key', $pin);
        $this->probe(new FakeProbe(certificate: new CertificateInfo(false, error: 'self-signed', publicKeyPin: $pin)));

        $tls = Whm::ping()->check('TLS');

        self::assertSame(CheckStatus::Pass, $tls?->status);
        self::assertStringContainsString('matches the pin', (string) $tls?->message);
    }

    #[Test]
    public function a_certificate_whose_key_does_not_match_the_pin_fails_and_shows_the_new_pin(): void
    {
        $pinned = 'sha256//'.base64_encode(str_repeat('k', 32));
        $presented = 'sha256//'.base64_encode(str_repeat('x', 32));
        $this->healthyServer();
        config()->set('cpanel-whm.connections.main.pinned_public_key', $pinned);
        $this->probe(new FakeProbe(certificate: new CertificateInfo(true, publicKeyPin: $presented)));

        $tls = Whm::ping()->check('TLS');

        self::assertSame(CheckStatus::Fail, $tls?->status);
        self::assertStringContainsString($presented, (string) $tls?->hint);
    }

    #[Test]
    public function the_tls_check_judges_trust_by_the_configured_ca_bundle(): void
    {
        $this->healthyServer();
        config()->set('cpanel-whm.connections.main.ca_bundle', __FILE__);
        $this->probe($probe = new FakeProbe);

        Whm::ping();

        self::assertSame(__FILE__, $probe->caBundleSeen);
    }

    #[Test]
    public function an_expiring_certificate_and_an_old_server_only_warn(): void
    {
        $this->healthyServer();
        config()->set('cpanel-whm.doctor.minimum_version', '11.140');
        $this->probe(new FakeProbe(certificate: new CertificateInfo(true, CarbonImmutable::now()->addDays(3))));

        $report = Whm::ping();

        self::assertSame(CheckStatus::Warn, $report->check('TLS')?->status);
        self::assertSame(CheckStatus::Warn, $report->check('Version')?->status);
        self::assertTrue($report->passed());
    }

    #[Test]
    public function plain_http_fails_the_check(): void
    {
        $this->healthyServer();
        config()->set('cpanel-whm.connections.main.host', 'http://203.0.113.5');

        $report = Whm::ping();

        self::assertFalse($report->passed());
        self::assertSame(CheckStatus::Fail, $report->check('TLS')?->status);
        self::assertStringContainsString('unencrypted', (string) $report->check('TLS')?->message);
        self::assertStringContainsString('allow_insecure_http', (string) $report->check('TLS')?->hint);
        self::assertStringContainsString('TLS n/a (http)', (string) $report->check('Config')?->message);
        self::assertStringContainsString('IP address', (string) $report->check('DNS')?->message);
    }

    #[Test]
    public function plain_http_only_warns_when_the_connection_opts_in(): void
    {
        $this->healthyServer();
        config()->set('cpanel-whm.connections.main.host', 'http://203.0.113.5');
        config()->set('cpanel-whm.connections.main.allow_insecure_http', true);

        $report = Whm::ping();

        self::assertTrue($report->passed());
        self::assertSame(CheckStatus::Warn, $report->check('TLS')?->status);
    }

    #[Test]
    public function unreadable_privileges_and_functions_only_warn_when_nothing_is_required(): void
    {
        Http::fake([
            '*/json-api/myprivs*' => Http::response(Responses::envelope(null, result: 0, reason: 'Access denied', command: 'myprivs')),
            '*/json-api/applist*' => Http::response(Responses::envelope(null, result: 0, reason: 'Access denied', command: 'applist')),
        ]);
        $this->healthyServer();

        $report = Whm::ping();

        self::assertTrue($report->passed());
        self::assertSame(CheckStatus::Warn, $report->check('Privileges')?->status);
        self::assertSame(CheckStatus::Warn, $report->check('Functions')?->status);
    }

    #[Test]
    public function network_checks_are_skipped_under_the_fake(): void
    {
        Whm::fake(['version' => ['version' => '11.134.0.5'], 'myprivs' => ['privileges' => [['all' => 1]]], 'applist' => ['app' => []]]);

        $report = Whm::ping();

        self::assertSame(CheckStatus::Skip, $report->check('DNS')?->status);
        self::assertTrue($report->passed());
        self::assertTrue($report->toArray()['passed']);
    }

    #[Test]
    public function json_output_covers_every_connection_with_all(): void
    {
        $this->healthyServer();

        $this->artisan('whm:doctor', ['--all' => true, '--json' => true])
            ->expectsOutputToContain('"connection": "ca-1"')
            ->assertSuccessful();
    }

    #[Test]
    public function json_output_with_all_is_a_list_even_for_one_connection(): void
    {
        $this->healthyServer();
        config()->set('cpanel-whm.connections', ['main' => config('cpanel-whm.connections.main')]);

        Artisan::call('whm:doctor', ['--all' => true, '--json' => true]);
        $all = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        Artisan::call('whm:doctor', ['--json' => true]);
        $one = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($all);
        self::assertTrue(array_is_list($all));
        self::assertSame('main', $all[0]['connection'] ?? null);
        self::assertIsArray($one);
        self::assertSame('main', $one['connection'] ?? null);
    }

    /**
     * A healthy server over the real HTTP transport (Http::fake) and a fake network probe.
     *
     * @param  array<string, int>  $privileges
     * @param  list<string>  $functions
     */
    public function healthyServer(array $privileges = ['all' => 1], array $functions = ['createacct', 'listaccts', 'version']): void
    {
        Http::fake([
            '*/json-api/version*' => Http::response(Responses::envelope(['version' => '11.134.0.5'])),
            '*/json-api/myprivs*' => Http::response(Responses::envelope(['privileges' => [$privileges]], command: 'myprivs')),
            '*/json-api/applist*' => Http::response(Responses::envelope(['app' => $functions], command: 'applist')),
        ]);
    }

    public function probe(NetworkProbe $probe): void
    {
        $this->instance(NetworkProbe::class, $probe);
    }
}
