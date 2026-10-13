<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature\Modules;

use Illuminate\Support\Facades\Event;
use Itxshakil\CpanelWhm\Events\AccountSuspended;
use Itxshakil\CpanelWhm\Events\AccountUnsuspended;
use Itxshakil\CpanelWhm\Exceptions\WhmCommandFailed;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Tests\Fixtures\Responses;
use Itxshakil\CpanelWhm\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class IdempotentChangesTest extends TestCase
{
    #[Test]
    public function an_account_is_suspended_only_when_it_is_not_already(): void
    {
        Event::fake([AccountSuspended::class]);
        $fake = Whm::fake([
            'accountsummary' => Whm::sequence(
                Whm::response(['acct' => [Responses::account(['user' => 'acme', 'suspended' => 0])]]),
                Whm::response(['acct' => [Responses::account(['user' => 'acme', 'suspended' => 1])]]),
            ),
            'suspendacct' => Whm::response(),
        ]);

        self::assertTrue(Whm::suspensions()->ensureSuspended('ACME', 'Unpaid'));
        self::assertFalse(Whm::suspensions()->ensureSuspended('acme', 'Unpaid'));

        $fake->assertCalledTimes('suspendacct', 1)
            ->assertCalled('suspendacct', static fn (array $params): bool => $params === ['user' => 'acme', 'reason' => 'Unpaid']);
        Event::assertDispatchedTimes(AccountSuspended::class, 1);
    }

    #[Test]
    public function an_account_is_unsuspended_only_when_it_is_suspended(): void
    {
        Event::fake([AccountUnsuspended::class]);
        $fake = Whm::fake([
            'accountsummary' => Whm::sequence(
                Whm::response(['acct' => [Responses::account(['user' => 'acme', 'suspended' => 1])]]),
                Whm::response(['acct' => [Responses::account(['user' => 'acme', 'suspended' => 0])]]),
            ),
            'unsuspendacct' => Whm::response(),
        ]);

        self::assertTrue(Whm::suspensions()->ensureUnsuspended('acme'));
        self::assertFalse(Whm::suspensions()->ensureUnsuspended('acme'));

        $fake->assertCalledTimes('unsuspendacct', 1);
        Event::assertDispatchedTimes(AccountUnsuspended::class, 1);
    }

    #[Test]
    public function a_missing_account_is_still_an_error(): void
    {
        Whm::fake([
            'accountsummary' => Whm::failure('Account does not exist.'),
            'suspendacct' => Whm::failure('You do not have a user named ghost.'),
        ]);

        $this->expectException(WhmCommandFailed::class);

        Whm::suspensions()->ensureSuspended('ghost');
    }

    #[Test]
    public function a_package_is_deleted_only_when_it_exists(): void
    {
        $fake = Whm::fake([
            'listpkgs' => Whm::sequence(
                Whm::response(['pkg' => [['name' => 'starter'], ['name' => 'pro']]]),
                Whm::response(['pkg' => [['name' => 'pro']]]),
            ),
            'killpkg' => Whm::response(),
        ]);

        self::assertTrue(Whm::packages()->deleteIfExists('starter'));
        self::assertFalse(Whm::packages()->deleteIfExists('starter'));

        $fake->assertCalledTimes('killpkg', 1)
            ->assertCalled('killpkg', static fn (array $params): bool => $params === ['pkgname' => 'starter']);
    }
}
