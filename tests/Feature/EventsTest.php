<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Itxshakil\CpanelWhm\Data\NewAccount;
use Itxshakil\CpanelWhm\Events\AccountCreated;
use Itxshakil\CpanelWhm\Events\AccountModified;
use Itxshakil\CpanelWhm\Events\AccountPackageChanged;
use Itxshakil\CpanelWhm\Events\AccountPasswordChanged;
use Itxshakil\CpanelWhm\Events\AccountRemoved;
use Itxshakil\CpanelWhm\Events\AccountSuspended;
use Itxshakil\CpanelWhm\Events\AccountUnsuspended;
use Itxshakil\CpanelWhm\Events\WhmFunctionSucceeded;
use Itxshakil\CpanelWhm\Events\WhmResponseReceived;
use Itxshakil\CpanelWhm\Exceptions\WhmCommandFailed;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class EventsTest extends TestCase
{
    #[Test]
    public function account_changes_dispatch_domain_events(): void
    {
        Event::fake([
            AccountCreated::class, AccountRemoved::class, AccountSuspended::class, AccountUnsuspended::class,
            AccountPackageChanged::class, AccountPasswordChanged::class,
        ]);
        Whm::fake([
            'createacct' => Whm::response(['nameserver' => 'ns1.example.com'], 'Account Creation Ok'),
            '*' => Whm::response(),
        ]);

        Whm::accounts()->create(new NewAccount('acme', 'acme.test', package: 'starter'));
        Whm::accounts()->changePackage('acme', 'pro');
        Whm::accounts()->changePassword('acme', 'n3w-Secret!');
        Whm::suspensions()->suspend('acme', 'Unpaid', lock: true);
        Whm::suspensions()->unsuspend('acme');
        Whm::accounts()->remove('acme');

        Event::assertDispatched(AccountCreated::class, static fn (AccountCreated $e): bool => $e->connection === 'main' && $e->account->username === 'acme');
        Event::assertDispatched(AccountPackageChanged::class, static fn (AccountPackageChanged $e): bool => $e->user === 'acme' && $e->package === 'pro');
        Event::assertDispatched(AccountPasswordChanged::class, static fn (AccountPasswordChanged $e): bool => $e->user === 'acme');
        Event::assertDispatched(AccountSuspended::class, static fn (AccountSuspended $e): bool => $e->reason === 'Unpaid' && $e->locked);
        Event::assertDispatched(AccountUnsuspended::class);
        Event::assertDispatched(AccountRemoved::class, static fn (AccountRemoved $e): bool => $e->user === 'acme');
    }

    #[Test]
    public function modifying_an_account_dispatches_the_redacted_changes(): void
    {
        Event::fake([AccountModified::class]);
        Whm::fake(['modifyacct' => Whm::response()]);

        Whm::accounts()->modify('acme', ['CONTACTEMAIL' => 'new@acme.example', 'password' => 'oops']);

        Event::assertDispatched(AccountModified::class, static fn (AccountModified $e): bool => $e->connection === 'main'
            && $e->user === 'acme'
            && $e->changes === ['CONTACTEMAIL' => 'new@acme.example', 'password' => '[REDACTED]']);
    }

    #[Test]
    public function nothing_is_dispatched_when_whm_refuses(): void
    {
        Event::fake([AccountSuspended::class]);
        Whm::fake(['suspendacct' => Whm::failure('No such user')]);

        try {
            Whm::suspensions()->suspend('ghost');
        } catch (WhmCommandFailed) {
        }

        Event::assertNotDispatched(AccountSuspended::class);
    }

    #[Test]
    public function events_follow_the_connection_used(): void
    {
        Event::fake([AccountUnsuspended::class]);
        Whm::fake(['*' => Whm::response()]);

        Whm::connection('ca-1')->suspensions()->unsuspend('acme');

        Event::assertDispatched(AccountUnsuspended::class, static fn (AccountUnsuspended $e): bool => $e->connection === 'ca-1');
    }

    #[Test]
    public function only_the_modules_fire_domain_events_and_every_path_fires_response_received(): void
    {
        Event::fake([AccountCreated::class, AccountSuspended::class, WhmResponseReceived::class]);
        Whm::fake(['*' => Whm::response()]);

        Whm::api()->accounts()->createacct(username: 'acme', domain: 'acme.test');
        Whm::call('suspendacct', ['user' => 'acme']);
        Whm::batch()->add('suspendacct', ['user' => 'other'])->send();

        Event::assertNotDispatched(AccountCreated::class);
        Event::assertNotDispatched(AccountSuspended::class);
        Event::assertDispatched(WhmResponseReceived::class, static fn (WhmResponseReceived $e): bool => $e->request->function === 'createacct');
        Event::assertDispatched(WhmResponseReceived::class, static fn (WhmResponseReceived $e): bool => $e->request->function === 'suspendacct'
            && $e->request->params['user'] === 'acme');
        Event::assertDispatched(WhmResponseReceived::class, static fn (WhmResponseReceived $e): bool => $e->request->function === 'batch');
        Event::assertDispatchedTimes(WhmResponseReceived::class, 3);
    }

    #[Test]
    public function every_path_that_changes_something_fires_function_succeeded(): void
    {
        Event::fake([WhmFunctionSucceeded::class, AccountCreated::class]);
        Whm::fake(['*' => Whm::response(), 'uapi_cpanel' => Whm::uapi()]);

        Whm::accounts()->create(new NewAccount(username: 'acme', domain: 'acme.test', password: 'S3cure!pass'));
        Whm::api()->accounts()->createacct(username: 'other', domain: 'other.test', password: 'S3cure!pass');
        Whm::call('suspendacct', ['user' => 'acme']);
        Whm::asUser('acme')->uapi('Email', 'add_pop', ['email' => 'info', 'password' => 'S3cure!pass']);
        Whm::batch()->add('suspendacct', ['user' => 'third'])->add('unsuspendacct', ['user' => 'fourth'])->send();

        $fired = [];
        Event::assertDispatched(WhmFunctionSucceeded::class, static function (WhmFunctionSucceeded $e) use (&$fired): bool {
            $fired[] = [$e->connection, $e->function, $e->uapi(), $e->params['user'] ?? $e->params['username'] ?? $e->params['cpanel.user'] ?? null];

            return true;
        });

        // One per change, in order: a batch fires one per command and none for "batch" itself.
        self::assertSame([
            ['main', 'createacct', null, 'acme'],
            ['main', 'createacct', null, 'other'],
            ['main', 'suspendacct', null, 'acme'],
            ['main', 'uapi_cpanel', 'Email::add_pop', 'acme'],
            ['main', 'suspendacct', null, 'third'],
            ['main', 'unsuspendacct', null, 'fourth'],
        ], $fired);

        // Alongside the module's own event, not instead of it.
        Event::assertDispatchedTimes(AccountCreated::class, 1);
    }

    #[Test]
    public function function_succeeded_carries_redacted_parameters(): void
    {
        Event::fake([WhmFunctionSucceeded::class]);
        Whm::fake(['*' => Whm::response()]);

        Whm::call('passwd', ['user' => 'acme', 'password' => 'S3cure!pass']);

        Event::assertDispatched(WhmFunctionSucceeded::class, static fn (WhmFunctionSucceeded $e): bool => $e->function === 'passwd'
            && $e->params['user'] === 'acme'
            && ! str_contains(serialize($e), 'S3cure!pass'));
    }

    #[Test]
    public function reads_failures_and_failed_batch_commands_fire_no_function_succeeded(): void
    {
        Event::fake([WhmFunctionSucceeded::class]);
        Whm::fake([
            'version' => Whm::response(['version' => '11.138.0.10']),
            'uapi_cpanel' => Whm::uapi([]),
            'suspendacct' => Whm::failure('nope'),
            'unsuspendacct' => Whm::response(),
        ]);

        Whm::server()->version();
        Whm::asUser('acme')->uapi('Email', 'list_pops');
        rescue(static fn () => Whm::call('suspendacct', ['user' => 'acme']), report: false);
        Whm::batch()->add('version')->add('suspendacct', ['user' => 'acme'])->add('unsuspendacct', ['user' => 'acme'])->send();

        // Only the batch's one successful change.
        Event::assertDispatchedTimes(WhmFunctionSucceeded::class, 1);
        Event::assertDispatched(WhmFunctionSucceeded::class, static fn (WhmFunctionSucceeded $e): bool => $e->function === 'unsuspendacct');
    }
}
