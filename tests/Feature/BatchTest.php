<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Itxshakil\CpanelWhm\Events\WhmRequestSending;
use Itxshakil\CpanelWhm\Exceptions\StrayWhmCall;
use Itxshakil\CpanelWhm\Exceptions\WhmCommandFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmPermissionDenied;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Tests\Fixtures\Responses;
use Itxshakil\CpanelWhm\Tests\TestCase;
use Itxshakil\CpanelWhm\WhmResponse;
use PHPUnit\Framework\Attributes\Test;

final class BatchTest extends TestCase
{
    #[Test]
    public function commands_are_sent_in_one_post_with_their_parameters_encoded(): void
    {
        Http::fake(['*' => Http::response(Responses::envelope(['result' => [
            Responses::envelope(['version' => '11.138.0.10'], command: 'version'),
            Responses::envelope(['acct' => [['user' => 'acme']]], command: 'accountsummary'),
        ]], command: 'batch'))]);

        $results = Whm::batch()
            ->add('version')
            ->add('accountsummary', ['user' => 'acme', 'skip' => null, 'flag' => true])
            ->send();

        Http::assertSent(static fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?: '', '/json-api/batch')
            && $request['command'] === 'version'
            && $request['command-1'] === 'accountsummary?user=acme&flag=1');

        self::assertCount(2, $results);
        self::assertTrue($results->successful());
        self::assertSame('11.138.0.10', $results[0]->get('version'));
        self::assertSame('acme', $results->get(1)->get('acct.0.user'));
        self::assertSame(['version', 'accountsummary'], array_map(static fn (WhmResponse $r): ?string => $r->command(), iterator_to_array($results)));
    }

    #[Test]
    public function abort_on_error_is_sent_when_asked(): void
    {
        $fake = Whm::fake(['version' => Whm::response(['version' => '1'])]);

        Whm::batch()->add('version')->abortOnError()->send();

        $fake->assertCalled('batch', static fn (array $params): bool => $params['abort_on_error'] === 1);
    }

    #[Test]
    public function failed_commands_are_reported_and_can_be_thrown(): void
    {
        Whm::fake([
            'version' => Whm::response(['version' => '1']),
            'removeacct' => Whm::failure('Access denied'),
            'accountsummary' => Whm::failure('Account does not exist.'),
        ]);

        $results = Whm::batch()->add('version')->add('removeacct', ['user' => 'acme'])->add('accountsummary', ['user' => 'ghost'])->send();

        self::assertFalse($results->successful());
        self::assertSame([1, 2], array_keys($results->failures()));
        self::assertInstanceOf(WhmPermissionDenied::class, $results->failures()[1]);
        self::assertSame('Account does not exist.', $results->failures()[2]->reason());

        $this->expectException(WhmPermissionDenied::class);
        $results->throw();
    }

    #[Test]
    public function the_fake_answers_each_command_and_records_it(): void
    {
        $fake = Whm::fake([
            'version' => Whm::response(['version' => '11.138.0.10']),
            'accountsummary' => static fn ($request) => Whm::response(['acct' => [['user' => $request->params['user']]]]),
        ]);

        $results = Whm::batch()->add('version')->add('accountsummary', ['user' => 'acme'])->send();

        self::assertSame('acme', $results[1]->get('acct.0.user'));
        $fake->assertCalledTimes('batch', 1)
            ->assertCalled('accountsummary', static fn (array $params): bool => $params === ['user' => 'acme'])
            ->assertCalledTimes('version', 1);
    }

    #[Test]
    public function the_fake_stops_after_a_failure_when_aborting_on_error(): void
    {
        $fake = Whm::fake(['*' => Whm::failure('nope')]);

        $results = Whm::batch()->add('version')->add('gethostname')->abortOnError()->send();

        self::assertCount(1, $results);
        $fake->assertNotCalled('gethostname');
    }

    #[Test]
    public function the_fake_still_refuses_commands_it_has_no_answer_for(): void
    {
        Whm::fake(['version' => Whm::response()]);

        $this->expectException(StrayWhmCall::class);

        Whm::batch()->add('version')->add('removeacct', ['user' => 'acme'])->send();
    }

    #[Test]
    public function secrets_inside_commands_are_redacted_in_events(): void
    {
        Event::fake([WhmRequestSending::class]);
        Whm::fake(['passwd' => Whm::response()]);

        Whm::batch()->add('passwd', ['user' => 'acme', 'password' => 'hunter2'])->send();

        Event::assertDispatched(WhmRequestSending::class, static fn (WhmRequestSending $event): bool => $event->request->function === 'batch'
            && ! str_contains((string) json_encode($event->request->params), 'hunter2'));
    }

    #[Test]
    public function an_empty_batch_sends_nothing(): void
    {
        $fake = Whm::fake();

        self::assertCount(0, Whm::batch()->send());
        $fake->assertNothingSent();
    }

    #[Test]
    public function a_failed_batch_with_results_still_returns_them(): void
    {
        Http::fake(['*' => Http::response(Responses::envelope(['result' => [
            Responses::envelope(null, result: 0, reason: 'nope', command: 'version'),
        ]], result: 0, reason: 'One or more commands failed.', command: 'batch'))]);

        $results = Whm::batch()->add('version')->send();

        self::assertInstanceOf(WhmCommandFailed::class, $results->failures()[0]);
    }
}
