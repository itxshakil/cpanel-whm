<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Itxshakil\CpanelWhm\Data\NewAccount;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Events\WhmRequestFailed;
use Itxshakil\CpanelWhm\Events\WhmRequestSending;
use Itxshakil\CpanelWhm\Events\WhmResponseReceived;
use Itxshakil\CpanelWhm\Exceptions\WhmAuthenticationFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmCommandFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmConnectionFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmHttpError;
use Itxshakil\CpanelWhm\Exceptions\WhmPermissionDenied;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Tests\Fixtures\Responses;
use Itxshakil\CpanelWhm\Tests\TestCase;
use Itxshakil\CpanelWhm\WhmRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class HttpTransportTest extends TestCase
{
    #[Test]
    public function every_call_sends_the_token_header_and_api_version_1(): void
    {
        Http::fake(['server.example.com:2087/*' => Http::response(Responses::envelope(['version' => '11.134.0.5']))]);

        self::assertSame('11.134.0.5', Whm::server()->version());

        Http::assertSent(static fn (Request $request): bool => $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://server.example.com:2087/json-api/version?')
            && (int) $request['api.version'] === 1
            && $request->header('Authorization') === ['whm root:SECRET-TOKEN-123']);
    }

    #[Test]
    public function post_calls_are_form_encoded_so_secrets_stay_out_of_the_url(): void
    {
        Http::fake(['*' => Http::response(Responses::envelope([], command: 'passwd'))]);

        Whm::accounts()->changePassword('acme', 'hunter2');

        Http::assertSent(static fn (Request $request): bool => $request->method() === 'POST'
            && ! str_contains($request->url(), 'hunter2')
            && $request->isForm()
            && $request['password'] === 'hunter2'
            && (int) $request['api.version'] === 1);
    }

    #[Test]
    public function result_0_becomes_whm_command_failed_with_the_reason(): void
    {
        Http::fake(['*' => Http::response(Responses::envelope(null, result: 0, reason: 'The account already exists.', command: 'createacct'))]);

        try {
            Whm::call('createacct', ['username' => 'acme'], HttpMethod::Post);
            self::fail('Expected WhmCommandFailed');
        } catch (WhmCommandFailed $whmCommandFailed) {
            self::assertSame('WHM createacct failed: The account already exists.', $whmCommandFailed->getMessage());
            self::assertSame('The account already exists.', $whmCommandFailed->reason());
            self::assertSame('createacct', $whmCommandFailed->function());
        }
    }

    #[Test]
    public function permission_failures_are_recognised(): void
    {
        Http::fake(['*' => Http::response(Responses::envelope(null, result: 0, reason: 'Access denied', command: 'removeacct'))]);

        $this->expectException(WhmPermissionDenied::class);

        Whm::call('removeacct');
    }

    #[Test]
    #[DataProvider('authStatuses')]
    public function http_401_and_403_become_authentication_failures_with_a_hint(int $status): void
    {
        Http::fake(['*' => Http::response('Access denied', $status)]);

        try {
            Whm::call('version');
            self::fail('Expected WhmAuthenticationFailed');
        } catch (WhmAuthenticationFailed $whmAuthenticationFailed) {
            self::assertSame($status, $whmAuthenticationFailed->status());
            self::assertStringContainsString('Manage API Tokens', $whmAuthenticationFailed->hint());
        }
    }

    /** @return array<string, array{int}> */
    public static function authStatuses(): array
    {
        return ['401' => [401], '403' => [403]];
    }

    #[Test]
    public function server_errors_and_non_json_answers_are_http_errors(): void
    {
        Http::fake(['*' => Http::sequence()->push('oops', 500)->push('<html>login</html>', 200)]);

        try {
            Whm::call('version');
            self::fail('Expected WhmHttpError');
        } catch (WhmHttpError $whmHttpError) {
            self::assertStringContainsString('HTTP 500', $whmHttpError->getMessage());
            self::assertStringContainsString('error_log', (string) $whmHttpError->hint());
        }

        try {
            Whm::call('version');
            self::fail('Expected WhmHttpError');
        } catch (WhmHttpError $whmHttpError) {
            self::assertStringContainsString('not JSON', $whmHttpError->getMessage());
            self::assertStringContainsString('login page', (string) $whmHttpError->hint());
        }
    }

    #[Test]
    public function an_http_error_keeps_the_start_of_the_body_with_secrets_masked(): void
    {
        $page = '<html>cpsrvd error at https://h:2087/cpsess1234567890/json-api/version '.str_repeat('x', 3000).'</html>';
        Http::fake(['*' => Http::sequence()
            ->push($page, 502)
            ->push(['data' => ['reason' => 'Upgrade in progress.', 'token' => 'SECRET'], 'metadata' => []], 503)]);

        foreach ([1, 2] as $attempt) {
            try {
                Whm::withoutRetries()->call('version');
                self::fail('Expected WhmHttpError');
            } catch (WhmHttpError $whmHttpError) {
                $body = (string) $whmHttpError->body();

                if ($attempt === 1) {
                    self::assertStringStartsWith('<html>cpsrvd error', $body);
                    self::assertStringNotContainsString('1234567890', $body);
                    self::assertSame(WhmHttpError::BODY_LIMIT + 1, mb_strlen($body));
                } else {
                    // A JSON error body gives the message its reason, from data.reason when metadata has none.
                    self::assertStringContainsString('HTTP 503 for version: Upgrade in progress.', $whmHttpError->getMessage());
                    self::assertStringNotContainsString('SECRET', $body);
                }
            }
        }
    }

    #[Test]
    public function a_404_has_no_hint(): void
    {
        self::assertNull(WhmHttpError::forStatus(404, 'version')->hint());
        self::assertSame('version', WhmHttpError::forStatus(404, 'version')->function());
    }

    #[Test]
    public function connection_failures_do_not_leak_the_query_string(): void
    {
        Http::fake(static fn () => throw new ConnectionException('cURL error 7: Failed to connect for https://server.example.com:2087/json-api/passwd?password=hunter2'));

        try {
            Whm::call('passwd', ['password' => 'hunter2']);
            self::fail('Expected WhmConnectionFailed');
        } catch (WhmConnectionFailed $whmConnectionFailed) {
            self::assertStringNotContainsString('hunter2', $whmConnectionFailed->getMessage());
            self::assertStringContainsString('server.example.com:2087', $whmConnectionFailed->getMessage());
            self::assertStringContainsString('whm:doctor', $whmConnectionFailed->hint());
        }
    }

    #[Test]
    public function tls_verification_can_be_turned_off(): void
    {
        config()->set('cpanel-whm.connections.main.verify_tls', false);
        Http::fake(['*' => Http::response(Responses::envelope(['version' => '11.134.0.5']))]);

        Whm::server()->version();

        Http::assertSentCount(1);
    }

    #[Test]
    public function a_ca_bundle_and_a_pinned_key_reach_the_http_client(): void
    {
        $pin = 'sha256//'.base64_encode(str_repeat('k', 32));
        config()->set('cpanel-whm.connections.main.ca_bundle', __FILE__);
        config()->set('cpanel-whm.connections.main.pinned_public_key', $pin);

        $options = [];
        Http::fake(static function (Request $request, array $sent) use (&$options) {
            $options = $sent;

            return Http::response(Responses::envelope(['version' => '11.134.0.5']));
        });

        Whm::server()->version();

        self::assertSame(__FILE__, $options['verify'] ?? null);
        self::assertSame($pin, $options['curl'][CURLOPT_PINNEDPUBLICKEY] ?? null);
    }

    #[Test]
    public function a_client_copy_can_use_shorter_timeouts(): void
    {
        $sent = [];
        Http::fake(static function (Request $request, array $options) use (&$sent) {
            $sent[] = [$options['timeout'] ?? null, $options['connect_timeout'] ?? null];

            return Http::response(Responses::envelope(['version' => '11.134.0.5']));
        });

        Whm::timeout(5, connect: 2)->server()->version();
        Whm::timeout(5, connect: 2)->call('version', timeout: 9);
        Whm::server()->version();

        // The copy's timeouts, a per-call timeout winning over them, and the original client untouched.
        self::assertSame([[5, 2], [9, 2], [30, 10]], $sent);
    }

    #[Test]
    public function createacct_keeps_its_long_timeout_on_a_short_timeout_client(): void
    {
        $fake = Whm::fake(['createacct' => Whm::response(['user' => 'acme'])]);

        Whm::timeout(5)->accounts()->create(new NewAccount(username: 'acme', domain: 'acme.example', password: 'S3cure!pass'));

        $fake->assertCalled('createacct', static fn (array $params, WhmRequest $request): bool => $request->timeout === 120);
    }

    #[Test]
    public function events_are_fired_for_sending_receiving_and_failing(): void
    {
        Event::fake([WhmRequestSending::class, WhmResponseReceived::class, WhmRequestFailed::class]);
        Http::fake(['*' => Http::sequence()
            ->push(Responses::envelope(['version' => '11.134.0.5']))
            ->push(Responses::envelope(null, result: 0, reason: 'nope'))]);

        Whm::server()->version();
        rescue(static fn () => Whm::call('passwd', ['password' => 'secret']), report: false);

        Event::assertDispatched(WhmRequestSending::class, 2);
        Event::assertDispatched(WhmResponseReceived::class, static fn (WhmResponseReceived $event): bool => $event->request->function === 'version');
        Event::assertDispatched(WhmRequestFailed::class, static fn (WhmRequestFailed $event): bool => $event->exception instanceof WhmCommandFailed
            && $event->request->redactedParams() === ['password' => '[REDACTED]']);
    }
}
