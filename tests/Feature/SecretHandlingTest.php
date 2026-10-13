<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature;

use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Itxshakil\CpanelWhm\Data\NewAccount;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Events\AccountCreated;
use Itxshakil\CpanelWhm\Events\WhmRequestFailed;
use Itxshakil\CpanelWhm\Events\WhmRequestSending;
use Itxshakil\CpanelWhm\Events\WhmResponseReceived;
use Itxshakil\CpanelWhm\Exceptions\TransportFailure;
use Itxshakil\CpanelWhm\Exceptions\UapiCallFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmCommandFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmConnectionFailed;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Tests\Fixtures\Responses;
use Itxshakil\CpanelWhm\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Secrets must never reach a URL, an event, a log or an exception chain.
 */
final class SecretHandlingTest extends TestCase
{
    #[Test]
    public function a_raw_call_with_a_password_is_sent_as_post_even_without_a_method(): void
    {
        Http::fake(['*' => Http::response(Responses::envelope([], command: 'passwd'))]);

        Whm::call('passwd', ['user' => 'acme', 'password' => 'hunter2']);

        Http::assertSent(static fn (Request $request): bool => $request->method() === 'POST'
            && ! str_contains($request->url(), 'hunter2')
            && $request['password'] === 'hunter2');
    }

    #[Test]
    public function an_explicit_get_is_upgraded_to_post_when_a_parameter_is_secret(): void
    {
        Http::fake(['*' => Http::response(Responses::envelope([], command: 'passwd'))]);

        Whm::call('passwd', ['user' => 'acme', 'password' => 'hunter2'], HttpMethod::Get);

        Http::assertSent(static fn (Request $request): bool => $request->method() === 'POST'
            && ! str_contains($request->url(), 'hunter2'));
    }

    #[Test]
    public function the_method_defaults_to_the_one_cpanel_documents(): void
    {
        Http::fake(['*' => Http::response(Responses::envelope([]))]);

        Whm::call('version');
        Whm::call('removeacct', ['user' => 'acme']);

        $methods = Http::recorded()->map(static fn (array $pair): string => $pair[0]->method())->all();

        self::assertSame(['GET', 'POST'], $methods);
    }

    #[Test]
    public function an_unknown_function_is_sent_as_post(): void
    {
        Http::fake(['*' => Http::response(Responses::envelope([]))]);

        Whm::call('some_plugin_function', ['setting' => 'value']);

        Http::assertSent(static fn (Request $request): bool => $request->method() === 'POST');
    }

    #[Test]
    public function login_sessions_are_created_with_post(): void
    {
        Http::fake(['*' => Http::response(Responses::envelope([
            'url' => 'https://server.example.com:2083/cpsess1234567890/login/?session=acme:abc',
            'expires' => 1_900_000_000,
        ], command: 'create_user_session'))]);

        Whm::sessions()->create('acme');

        Http::assertSent(static fn (Request $request): bool => $request->method() === 'POST');
    }

    #[Test]
    public function the_dry_run_of_whm_call_shows_the_method_that_will_be_used(): void
    {
        $this->artisan('whm:call', ['function' => 'passwd', 'params' => ['user=acme', 'password=hunter2'], '--dry-run' => true])
            ->expectsOutputToContain('POST https://server.example.com:2087/json-api/passwd')
            ->doesntExpectOutputToContain('hunter2')
            ->assertSuccessful();
    }

    #[Test]
    public function events_carry_redacted_parameters_and_responses(): void
    {
        Event::fake([WhmRequestSending::class, WhmResponseReceived::class, WhmRequestFailed::class]);
        Http::fake(['*' => Http::sequence()
            ->push(Responses::envelope(['token' => 'NEW-TOKEN-VALUE', 'create_time' => 1], command: 'api_token_create'))
            ->push(Responses::envelope(null, result: 0, reason: 'nope', command: 'passwd'))]);

        $created = Whm::call('api_token_create', ['token_name' => 'ci']);
        rescue(static fn () => Whm::call('passwd', ['user' => 'acme', 'password' => 'hunter2']), report: false);

        // The caller still gets the real token.
        self::assertSame('NEW-TOKEN-VALUE', $created->get('token'));

        $events = [
            ...Event::dispatched(WhmRequestSending::class)->flatten()->all(),
            ...Event::dispatched(WhmResponseReceived::class)->flatten()->all(),
            ...Event::dispatched(WhmRequestFailed::class)->flatten()->all(),
        ];

        self::assertCount(4, $events);

        foreach ($events as $event) {
            // Stack trace arguments are left to zend.exception_ignore_args, which production PHP sets.
            $serialized = print_r($event->request, true)
                .print_r($event->response ?? null, true)
                .(isset($event->exception) ? $event->exception->getMessage() : '');

            self::assertStringNotContainsString('hunter2', $serialized);
            self::assertStringNotContainsString('NEW-TOKEN-VALUE', $serialized);
        }
    }

    #[Test]
    public function the_password_createacct_echoes_in_its_output_stays_out_of_events(): void
    {
        Event::fake([AccountCreated::class, WhmResponseReceived::class]);
        Whm::fake(['createacct' => Whm::response([], 'Account Creation Ok', [
            'output' => ['raw' => "| Domain: acme.test\n| UserName: acme\n| PassWord: s3cret-Generated!\n| CpanelMod: paper_lantern\n"],
        ])]);

        $created = Whm::accounts()->create(new NewAccount('acme', 'acme.test', password: 's3cret-Generated!'));

        // The caller still gets the password it asked for.
        self::assertSame('s3cret-Generated!', $created->password);

        $stored = $created->withoutPassword();
        self::assertStringContainsString('| PassWord: [REDACTED]', (string) $stored->rawOutput);
        self::assertStringContainsString('| UserName: acme', (string) $stored->rawOutput);

        $serialized = serialize(Event::dispatched(AccountCreated::class)->flatten()->all())
            .serialize(Event::dispatched(WhmResponseReceived::class)->flatten()->all())
            .print_r($stored, true);

        self::assertStringNotContainsString('s3cret-Generated!', $serialized);
    }

    #[Test]
    public function a_failed_batch_does_not_leak_the_secrets_its_other_commands_returned(): void
    {
        Event::fake([WhmRequestFailed::class]);
        Http::fake(['*' => Http::response(Responses::envelope(['result' => [
            Responses::envelope(['token' => 'NEW-TOKEN-VALUE'], command: 'api_token_create'),
            Responses::envelope(['url' => 'https://server.example.com:2083/cpsess1234567890/login/?session=acme:SESSION-ID'], command: 'create_user_session'),
            Responses::envelope(null, result: 0, reason: 'nope', command: 'removeacct'),
        ]], result: 0, reason: 'One or more commands failed.', command: 'batch'))]);

        $results = Whm::batch()
            ->add('api_token_create', ['token_name' => 'ci'])
            ->add('create_user_session', ['user' => 'acme', 'service' => 'cpaneld'])
            ->add('removeacct', ['user' => 'ghost'])
            ->send();

        // The caller still gets the real values.
        self::assertSame('NEW-TOKEN-VALUE', $results[0]->get('token'));

        Event::assertDispatched(WhmRequestFailed::class, static function (WhmRequestFailed $event): bool {
            if (! $event->exception instanceof WhmCommandFailed) {
                return false;
            }

            $serialized = print_r($event->exception->response(), true);

            return ! str_contains($serialized, 'NEW-TOKEN-VALUE') && ! str_contains($serialized, 'SESSION-ID') && ! str_contains($serialized, '1234567890');
        });
    }

    #[Test]
    public function a_failed_uapi_call_reaches_events_with_its_data_redacted(): void
    {
        Event::fake([WhmRequestFailed::class]);
        Http::fake(['*' => Http::response(Responses::envelope(['uapi' => [
            'status' => 0,
            'errors' => ['nope'],
            'data' => ['password' => 'hunter2'],
        ]], command: 'uapi_cpanel'))]);

        $exception = null;

        try {
            Whm::asUser('acme')->uapi('Email', 'add_pop', ['email' => 'info']);
        } catch (UapiCallFailed $uapiCallFailed) {
            $exception = $uapiCallFailed;
        }

        self::assertInstanceOf(UapiCallFailed::class, $exception);
        self::assertSame(['password' => 'hunter2'], $exception->result()->data);

        Event::assertDispatched(WhmRequestFailed::class, static fn (WhmRequestFailed $event): bool => $event->exception instanceof UapiCallFailed
            && $event->exception->result()->data === ['password' => '[REDACTED]']);
    }

    #[Test]
    public function an_access_hash_returned_by_whm_never_reaches_an_event(): void
    {
        Event::fake([WhmResponseReceived::class]);
        Http::fake(['*' => Http::response(Responses::envelope(['accesshash' => "ACCESS-HASH-LINE-1\nACCESS-HASH-LINE-2"], command: 'accesshash'))]);

        $response = Whm::call('accesshash', ['generate' => 0]);

        self::assertStringContainsString('ACCESS-HASH-LINE-1', (string) $response->get('accesshash'));

        Event::assertDispatched(WhmResponseReceived::class, static fn (WhmResponseReceived $event): bool => ! str_contains(print_r($event->response, true), 'ACCESS-HASH'));
    }

    #[Test]
    public function connection_failures_do_not_keep_the_raw_curl_error_in_the_chain(): void
    {
        Http::fake(static fn () => throw new ConnectionException(
            'cURL error 7: Failed to connect to server.example.com port 2087: https://server.example.com:2087/json-api/listaccts?api.version=1&search=private-customer',
        ));

        try {
            Whm::call('listaccts', ['search' => 'private-customer']);
            self::fail('Expected WhmConnectionFailed');
        } catch (WhmConnectionFailed $whmConnectionFailed) {
            for ($exception = $whmConnectionFailed; $exception !== null; $exception = $exception->getPrevious()) {
                self::assertStringNotContainsString('private-customer', $exception->getMessage());
            }

            self::assertFalse($whmConnectionFailed->mayHaveReachedServer());
        }
    }

    #[Test]
    public function a_read_timeout_says_the_request_may_have_reached_whm(): void
    {
        Http::fake(static fn () => throw new ConnectionException(
            'cURL error 28: Operation timed out after 120001 milliseconds with 0 bytes received',
        ));

        try {
            Whm::call('createacct', ['username' => 'acme', 'domain' => 'acme.example']);
            self::fail('Expected WhmConnectionFailed');
        } catch (WhmConnectionFailed $whmConnectionFailed) {
            self::assertTrue($whmConnectionFailed->mayHaveReachedServer());
            self::assertStringContainsString('may have', (string) $whmConnectionFailed->hint());
        }
    }

    #[Test]
    public function an_empty_reply_from_guzzle_becomes_a_redacted_connection_failure(): void
    {
        $url = 'https://server.example.com:2087/json-api/listaccts?api.version=1&search=private-customer';
        Http::fake(static fn () => throw new GuzzleRequestException('cURL error 52: Empty reply from server for '.$url, new GuzzleRequest('GET', $url)));

        try {
            Whm::call('listaccts', ['search' => 'private-customer']);
            self::fail('Expected WhmConnectionFailed');
        } catch (WhmConnectionFailed $whmConnectionFailed) {
            self::assertTrue($whmConnectionFailed->mayHaveReachedServer());
            self::assertStringNotContainsString('private-customer', $whmConnectionFailed->getMessage());

            // The chain keeps a safe stand-in, never the Guzzle exception with its request.
            $previous = $whmConnectionFailed->getPrevious();
            self::assertInstanceOf(TransportFailure::class, $previous);
            // Laravel's HTTP client wraps the Guzzle error in its ConnectionException.
            self::assertContains($previous->originalClass(), [ConnectionException::class, GuzzleRequestException::class]);
            self::assertSame(52, $previous->curlError());
            self::assertNull($previous->getPrevious());
            self::assertStringNotContainsString('private-customer', $previous->getMessage());
        }
    }

    #[Test]
    public function a_connection_failure_exposes_the_curl_error_number(): void
    {
        Http::fake(static fn () => throw new ConnectionException('cURL error 7: Failed to connect to server.example.com port 2087'));

        try {
            Whm::withoutRetries()->call('version');
            self::fail('Expected WhmConnectionFailed');
        } catch (WhmConnectionFailed $whmConnectionFailed) {
            self::assertSame(7, $whmConnectionFailed->curlError());
            self::assertInstanceOf(TransportFailure::class, $whmConnectionFailed->getPrevious());
            self::assertSame(ConnectionException::class, $whmConnectionFailed->getPrevious()->originalClass());
        }
    }

    #[Test]
    public function a_connect_timeout_did_not_reach_whm(): void
    {
        Http::fake(static fn () => throw new ConnectionException(
            'cURL error 28: Connection timed out after 10001 milliseconds',
        ));

        try {
            Whm::call('createacct', ['username' => 'acme', 'domain' => 'acme.example']);
            self::fail('Expected WhmConnectionFailed');
        } catch (WhmConnectionFailed $whmConnectionFailed) {
            self::assertFalse($whmConnectionFailed->mayHaveReachedServer());
        }
    }
}
