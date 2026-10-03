<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature\Modules;

use Carbon\CarbonImmutable;
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
    public function an_account_is_packaged_in_the_background(): void
    {
        $fake = Whm::fake([
            'start_background_pkgacct' => Whm::response(['session_id' => 'acme2026abc']),
            'get_pkgacct_session_state' => Whm::response(['state' => 'RUNNING']),
        ]);

        self::assertSame('acme2026abc', Whm::backups()->backupAccount('acme', skipHomeDir: true, options: ['skiplogs' => true]));
        self::assertSame('RUNNING', Whm::backups()->backupStatus('acme2026abc'));

        $fake->assertCalled('start_background_pkgacct', static fn (array $p): bool => $p === [
            'user' => 'acme', 'compressionsetting' => 'compress', 'skiphomedir' => 1, 'skiplogs' => 1,
        ]);
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
        Whm::fake([
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
}
