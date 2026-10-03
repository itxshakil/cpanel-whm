<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Itxshakil\CpanelWhm\Data\NewAccount;
use Itxshakil\CpanelWhm\Events\AccountCreated;
use Itxshakil\CpanelWhm\Events\AccountPackageChanged;
use Itxshakil\CpanelWhm\Events\AccountPasswordChanged;
use Itxshakil\CpanelWhm\Events\AccountRemoved;
use Itxshakil\CpanelWhm\Events\AccountSuspended;
use Itxshakil\CpanelWhm\Events\AccountUnsuspended;
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
}
