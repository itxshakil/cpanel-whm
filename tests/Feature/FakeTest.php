<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature;

use Itxshakil\CpanelWhm\Contracts\WhmClient;
use Itxshakil\CpanelWhm\Exceptions\StrayWhmCall;
use Itxshakil\CpanelWhm\Exceptions\WhmCommandFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmConnectionFailed;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Tests\TestCase;
use Itxshakil\CpanelWhm\WhmRequest;
use OutOfBoundsException;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;

final class FakeTest extends TestCase
{
    #[Test]
    public function it_records_calls_and_asserts_on_their_parameters(): void
    {
        $fake = Whm::fake(['suspendacct' => Whm::response()]);

        Whm::suspensions()->suspend('Acme', 'Unpaid invoice');

        $fake->assertCalled('suspendacct', static fn (array $params): bool => $params === ['user' => 'acme', 'reason' => 'Unpaid invoice'])
            ->assertCalledTimes('suspendacct', 1)
            ->assertNotCalled('unsuspendacct')
            ->assertSentCount(1);
    }

    #[Test]
    public function a_call_without_a_response_fails_loudly(): void
    {
        Whm::fake();

        $this->expectException(StrayWhmCall::class);
        $this->expectExceptionMessage('Unexpected WHM call to [listips]');

        Whm::call('listips');
    }

    #[Test]
    public function stray_calls_can_be_allowed(): void
    {
        Whm::fake()->allowStrayCalls();

        self::assertTrue(Whm::call('listips')->successful());
    }

    #[Test]
    public function stray_call_errors_explain_how_to_fix_them(): void
    {
        self::assertStringContainsString('allowStrayCalls', StrayWhmCall::for('listips')->hint());
    }

    #[Test]
    public function faked_failures_go_through_the_real_parsing(): void
    {
        Whm::fake(['createacct' => Whm::failure('Sorry, that domain is already set up.')]);

        $this->expectException(WhmCommandFailed::class);
        $this->expectExceptionMessage('Sorry, that domain is already set up.');

        Whm::call('createacct');
    }

    #[Test]
    public function connection_errors_can_be_faked(): void
    {
        Whm::fake(['*' => Whm::connectionError('Connection timed out')]);

        $this->expectException(WhmConnectionFailed::class);
        $this->expectExceptionMessage('Connection timed out');

        Whm::call('version');
    }

    #[Test]
    public function sequences_play_in_order(): void
    {
        Whm::fake(['version' => Whm::sequence(Whm::failure('busy'), Whm::response(['version' => '11.134.0.5']))]);

        try {
            Whm::call('version');
            self::fail('Expected WhmCommandFailed');
        } catch (WhmCommandFailed) {
        }

        self::assertSame('11.134.0.5', Whm::server()->version());
    }

    #[Test]
    public function a_sequence_keeps_answering_from_when_empty_and_otherwise_runs_out(): void
    {
        Whm::fake([
            'version' => Whm::sequence(['version' => '1'])->whenEmpty(['version' => '2']),
            'applist' => Whm::sequence()->push(['app' => ['x']]),
        ]);

        self::assertSame('1', Whm::server()->version());
        self::assertSame('2', Whm::server()->version());
        self::assertSame('2', Whm::server()->version());
        self::assertSame(['x'], Whm::server()->functions());

        $this->expectException(OutOfBoundsException::class);

        Whm::server()->functions();
    }

    #[Test]
    public function closures_and_plain_arrays_are_accepted(): void
    {
        Whm::fake([
            'accountsummary' => static fn (WhmRequest $request): array => ['acct' => [['user' => $request->params['user'], 'domain' => 'x.example']]],
            'version' => ['version' => '11.130.0.1'],
        ]);

        self::assertSame('bob', Whm::accounts()->find('bob')?->username);
        self::assertSame('11.130.0.1', Whm::server()->version());
    }

    #[Test]
    public function the_fake_also_applies_to_the_injected_contract(): void
    {
        $fake = Whm::fake(['version' => ['version' => '1']]);

        $this->app->make(WhmClient::class)->server()->version();

        $fake->assertCalled('version');
    }

    #[Test]
    public function the_function_is_echoed_as_metadata_command(): void
    {
        Whm::fake(['listips' => Whm::response()]);

        self::assertSame('listips', Whm::call('listips')->command());
    }

    #[Test]
    public function recorded_requests_can_be_read_back(): void
    {
        $fake = Whm::fake(['*' => Whm::response()]);

        Whm::call('listips');
        Whm::call('version');

        self::assertCount(2, $fake->recorded());
        self::assertSame('version', $fake->recorded('version')[0]->function);
    }

    #[Test]
    public function assert_nothing_sent_fails_after_a_call(): void
    {
        $fake = Whm::fake(['*' => Whm::response()]);
        $fake->assertNothingSent();

        Whm::call('listips');

        $this->expectException(AssertionFailedError::class);
        $fake->assertNothingSent();
    }

    #[Test]
    public function assert_called_fails_when_no_call_matches(): void
    {
        $fake = Whm::fake(['*' => Whm::response()]);
        Whm::call('listips', ['a' => 1]);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('called with matching parameters');

        $fake->assertCalled('listips', static fn (array $params): bool => $params === ['a' => 2]);
    }
}
