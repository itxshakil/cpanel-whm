<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature\Modules;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Itxshakil\CpanelWhm\Exceptions\RestoreNotStarted;
use Itxshakil\CpanelWhm\Exceptions\WhmCommandFailed;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class BackupsAndResellersTest extends TestCase
{
    #[Test]
    public function backup_dates_and_users_are_read(): void
    {
        $fake = Whm::fake([
            'backup_config_get' => Whm::response(['backup_config' => ['backupenable' => 1, 'backupdir' => '/backup']]),
            'backup_date_list' => Whm::response(['backup_set' => ['2026-10-02T00:00:00.000Z', '2026-10-01', '2026-10-02']]),
            'backup_user_list' => Whm::response(['user' => [
                ['username' => 'acme', 'status' => 'active'],
                ['username' => 'gone', 'status' => 'inactive'],
                ['status' => 'active'],
            ]]),
        ]);

        self::assertSame('/backup', Whm::backups()->config()['backupdir']);
        self::assertSame(['2026-10-01', '2026-10-02'], Whm::backups()->dates()->all());
        self::assertSame(['acme' => 'active', 'gone' => 'inactive'], Whm::backups()->users(CarbonImmutable::parse('2026-10-02 13:00'))->all());

        $fake->assertCalled('backup_user_list', static fn (array $p): bool => $p === ['restore_point' => '2026-10-02']);
    }

    #[Test]
    public function an_account_is_restored_through_the_queue(): void
    {
        $fake = Whm::fake([
            'restore_queue_add_task' => Whm::response(['queue_id' => 'q-123']),
            'restore_queue_activate' => Whm::response(),
            'restore_queue_state' => Whm::response([
                'is_active' => 1,
                'pending' => [],
                'active' => [['user' => 'acme', 'restore_point' => '2026-10-02T00:00:00.000Z', 'options' => ['mysql' => 1, 'give_ip' => 0]]],
                'completed' => [['user' => 'older', 'restore_point' => '2026-09-01']],
            ]),
        ]);

        self::assertSame('q-123', Whm::backups()->restore('ACME', '2026-10-02T00:00:00Z', mail: false));
        Whm::backups()->restore('other', '2026-10-01', start: false);

        $queue = Whm::backups()->queue();
        self::assertTrue($queue->running);
        self::assertSame('active', $queue->stateOf('acme'));
        self::assertSame('completed', $queue->stateOf('older'));
        self::assertNull($queue->stateOf('nobody'));
        self::assertSame('2026-10-02', $queue->active[0]->restorePoint);
        self::assertSame(['mysql' => true, 'give_ip' => false], $queue->active[0]->options);

        $fake->assertCalled('restore_queue_add_task', static fn (array $p): bool => $p === [
            'user' => 'acme', 'restore_point' => '2026-10-02', 'mysql' => 1, 'mail_config' => 0, 'subdomains' => 1, 'give_ip' => 0,
        ])->assertCalledTimes('restore_queue_activate', 1);
    }

    #[Test]
    public function backup_dates_are_taken_in_the_servers_timezone_when_one_is_configured(): void
    {
        // 02:30 UTC on the 3rd is still the 2nd in Toronto.
        $moment = CarbonImmutable::parse('2026-10-03 02:30:00', 'UTC');
        $fake = Whm::fake(['backup_user_list' => Whm::response(['user' => []])]);

        Whm::backups()->users($moment);

        config()->set('cpanel-whm.connections.main.timezone', 'America/Toronto');
        Whm::purge();
        Whm::backups()->users($moment);
        Whm::backups()->users('2026-10-03');

        self::assertSame(
            ['2026-10-03', '2026-10-02', '2026-10-03'],
            array_map(static fn ($request): mixed => $request->params['restore_point'], $fake->recorded('backup_user_list')),
        );
        self::assertSame('UTC', $moment->getTimezone()->getName());
    }

    #[Test]
    public function a_queued_restore_whose_queue_fails_to_start_keeps_its_queue_id(): void
    {
        $fake = Whm::fake([
            'restore_queue_add_task' => Whm::response(['queue_id' => 'q-123']),
            'restore_queue_activate' => Whm::sequence(Whm::failure('The restore queue is locked.'), Whm::response()),
        ]);

        try {
            Whm::backups()->restore('acme', '2026-10-02');
            self::fail('Expected RestoreNotStarted');
        } catch (RestoreNotStarted $restoreNotStarted) {
            self::assertSame('q-123', $restoreNotStarted->queueId());
            self::assertStringContainsString('The restore queue is locked.', $restoreNotStarted->getMessage());
            self::assertInstanceOf(WhmCommandFailed::class, $restoreNotStarted->getPrevious());
            self::assertStringContainsString('activateQueue()', $restoreNotStarted->hint());
        }

        // Start the waiting task instead of queueing it again.
        Whm::backups()->activateQueue();

        $fake->assertCalledTimes('restore_queue_add_task', 1)
            ->assertCalledTimes('restore_queue_activate', 2);
    }

    #[Test]
    public function an_account_is_packaged_in_the_background(): void
    {
        $fake = Whm::fake([
            'start_background_pkgacct' => Whm::response(['session_id' => 'acme2026abc']),
            'get_pkgacct_session_state' => Whm::response(['state' => 'RUNNING']),
        ]);

        self::assertSame('acme2026abc', Whm::backups()->backupAccount('acme', skipHomeDir: true, options: ['skiplogs' => true]));
        self::assertSame('RUNNING', Whm::backups()->backupStatus('acme2026abc'));

        $fake->assertCalled('start_background_pkgacct', static fn (array $p): bool => $p === [
            'skiplogs' => 1, 'user' => 'acme', 'compressionsetting' => 'compress', 'skiphomedir' => 1,
        ]);
    }

    #[Test]
    public function backup_options_cannot_replace_the_normalised_user(): void
    {
        $fake = Whm::fake(['start_background_pkgacct' => Whm::response(['session_id' => 's1'])]);

        Whm::backups()->backupAccount('Acme', options: ['user' => 'other', 'skiplogs' => true]);

        $fake->assertCalled('start_background_pkgacct', static fn (array $p): bool => $p['user'] === 'acme' && $p['skiplogs'] === 1);
    }

    #[Test]
    public function resellers_are_listed_created_and_removed(): void
    {
        $fake = Whm::fake([
            'listresellers' => Whm::response(['reseller' => ['res1', 'res2']]),
            '*' => Whm::response(),
        ]);

        self::assertSame(['res1', 'res2'], Whm::resellers()->list()->all());
        Whm::resellers()->create('res3', ownAccount: true);
        Whm::resellers()->remove('res3');

        $fake->assertCalled('setupreseller', static fn (array $p): bool => $p === ['user' => 'res3', 'makeowner' => 1])
            ->assertCalled('unsetupreseller', static fn (array $p): bool => $p === ['user' => 'res3']);
    }

    #[Test]
    public function reseller_stats_and_counts_are_typed(): void
    {
        $fake = Whm::fake([
            'resellerstats' => Whm::response(['reseller' => [
                'user' => 'res1', 'diskused' => 5.69, 'diskquota' => 0, 'totaldiskalloc' => '1100', 'totalbwused' => '12.5',
                'bandwidthlimit' => 2000, 'totalbwalloc' => '500', 'diskoverselling' => 1, 'bwoverselling' => 0,
                'acct' => [['user' => 'acme', 'domain' => 'acme.test', 'diskused' => '1.57']],
            ]]),
            'acctcounts' => Whm::response(['reseller' => ['user' => 'res1', 'active' => 9, 'suspended' => 5, 'limit' => 25]]),
        ]);

        $stats = Whm::resellers()->stats('res1');
        self::assertSame(5.69, $stats->diskUsedMb);
        self::assertSame(1100.0, $stats->diskAllocatedMb);
        self::assertSame(2000, $stats->bandwidthLimitMb);
        self::assertTrue($stats->diskOverselling);
        self::assertSame('acme', $stats->accounts[0]['user']);

        $counts = Whm::resellers()->accountCounts('res1');
        self::assertSame(11, $counts->remaining());

        Whm::resellers()->stats('res1', includeDeleted: true);

        // Accounts removed this month stay out unless asked for.
        $fake->assertCalled('resellerstats', static fn (array $p): bool => $p === ['user' => 'res1', 'filter_deleted' => 1])
            ->assertCalled('resellerstats', static fn (array $p): bool => $p === ['user' => 'res1']);
    }

    #[Test]
    public function reseller_privileges_and_acl_lists_are_managed(): void
    {
        $fake = Whm::fake([
            'listacls' => Whm::response(['acl' => [
                ['name' => 'basic', 'privileges' => ['create-acct' => 1, 'kill-acct' => 0, 'list-accts' => 1]],
            ]]),
            '*' => Whm::response(),
        ]);

        self::assertSame(['basic' => ['create-acct', 'list-accts']], Whm::resellers()->acls()->all());

        Whm::resellers()->saveAcl('basic', ['create-acct', 'acl-list-accts']);
        Whm::resellers()->assignAcl('res1', 'basic');
        Whm::resellers()->setPrivileges('res1', ['suspend-acct']);

        $fake->assertCalled('saveacllist', static fn (array $p): bool => $p === ['acllist' => 'basic', 'acl-create-acct' => 1, 'acl-list-accts' => 1])
            ->assertCalled('setacls', static fn (array $p): bool => $p === ['reseller' => 'res1', 'acllist' => 'basic'])
            ->assertCalled('setacls', static fn (array $p): bool => $p === ['reseller' => 'res1', 'acl-suspend-acct' => 1]);
    }

    #[Test]
    public function reseller_limits_suspension_and_termination(): void
    {
        $fake = Whm::fake(['*' => Whm::response()]);

        Whm::resellers()->setLimits('res1', accounts: 25, diskMb: 10_240);
        Whm::resellers()->suspend('res1', 'Unpaid', lock: true);
        Whm::resellers()->unsuspend('res1', resellerOnly: true);
        Whm::resellers()->terminate('res1', includeOwnAccount: false);

        $fake->assertCalled('setresellerlimits', static fn (array $p): bool => $p === [
            'user' => 'res1', 'enable_account_limit' => 1, 'account_limit' => 25, 'enable_resource_limits' => 1, 'diskspace_limit' => 10_240,
        ])
            ->assertCalled('suspendreseller', static fn (array $p): bool => $p === ['user' => 'res1', 'reason' => 'Unpaid', 'disallow' => 1, 'reseller-only' => 0])
            ->assertCalled('unsuspendreseller', static fn (array $p): bool => $p === ['user' => 'res1', 'reseller-only' => 1])
            ->assertCalled('terminatereseller', static fn (array $p, $r): bool => $p === ['user' => 'res1', 'terminatereseller' => 0] && $r->timeout === 600);
    }

    #[Test]
    public function reseller_limits_can_be_turned_off(): void
    {
        $fake = Whm::fake(['setresellerlimits' => Whm::response()]);

        Whm::resellers()->setLimits('res1', limitAccounts: false, limitResources: false);
        Whm::resellers()->setLimits('res1', accounts: 10, limitResources: false);

        $fake->assertCalled('setresellerlimits', static fn (array $p): bool => $p === [
            'user' => 'res1', 'enable_account_limit' => 0, 'enable_resource_limits' => 0,
        ])->assertCalled('setresellerlimits', static fn (array $p): bool => $p === [
            'user' => 'res1', 'enable_account_limit' => 1, 'account_limit' => 10, 'enable_resource_limits' => 0,
        ]);
    }

    /**
     * setresellerlimits defaults both enable_* flags to 0, so a flag left out
     * would switch that limit off. The current state is read and sent back.
     */
    #[Test]
    public function limits_that_were_not_mentioned_keep_their_current_setting(): void
    {
        $fake = Whm::fake([
            'setresellerlimits' => Whm::response(),
            'acctcounts' => Whm::response(['reseller' => ['user' => 'res1', 'active' => 3, 'suspended' => 0, 'limit' => 25]]),
            'resellerstats' => Whm::response(['reseller' => ['user' => 'res1', 'diskquota' => 0, 'bandwidthlimit' => 0, 'acct' => []]]),
        ]);

        Whm::resellers()->setLimits('res1', diskMb: 1000);
        Whm::resellers()->setLimits('res1', accounts: 30);
        Whm::resellers()->setLimits('res1', overselling: true);

        $sent = array_map(static fn ($request): array => $request->params, $fake->recorded('setresellerlimits'));

        self::assertSame([
            // The account limit is on (25), so it stays on; resources become limited.
            ['user' => 'res1', 'enable_account_limit' => 1, 'enable_resource_limits' => 1, 'diskspace_limit' => 1000],
            // Disk and bandwidth are unlimited (0), so resource limits stay off.
            ['user' => 'res1', 'enable_account_limit' => 1, 'account_limit' => 30, 'enable_resource_limits' => 0],
            ['user' => 'res1', 'enable_account_limit' => 1, 'enable_resource_limits' => 0, 'enable_overselling' => 1],
        ], $sent);
        $fake->assertCalledTimes('acctcounts', 2)->assertCalledTimes('resellerstats', 2);
    }

    #[Test]
    public function limits_given_for_both_groups_need_no_extra_read(): void
    {
        $fake = Whm::fake(['setresellerlimits' => Whm::response()]);

        Whm::resellers()->setLimits('res1', accounts: 25, bandwidthMb: 5000);
        Whm::resellers()->setLimits('res1', limitAccounts: false, limitResources: true);

        $fake->assertNotCalled('acctcounts')->assertNotCalled('resellerstats')->assertCalledTimes('setresellerlimits', 2);
    }

    #[Test]
    public function a_limit_given_while_turning_it_off_is_refused(): void
    {
        $fake = Whm::fake();

        try {
            Whm::resellers()->setLimits('res1', diskMb: 1024, limitResources: false);
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException) {
            $fake->assertNothingSent();
        }
    }
}
