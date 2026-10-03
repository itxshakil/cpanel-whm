<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
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
