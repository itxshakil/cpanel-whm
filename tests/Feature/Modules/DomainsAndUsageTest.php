<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature\Modules;

use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class DomainsAndUsageTest extends TestCase
{
    #[Test]
    public function domains_are_listed_found_and_filtered_by_account(): void
    {
        Whm::fake(['get_domain_info' => Whm::response(['domains' => [
            ['domain' => 'acme.test', 'user' => 'acme', 'user_owner' => 'root', 'domain_type' => 'main', 'docroot' => '/home/acme/public_html', 'ipv4' => '203.0.113.10', 'php_version' => 'ea-php83'],
            ['domain' => 'shop.acme.test', 'user' => 'acme', 'domain_type' => 'sub', 'parent_domain' => 'acme.test'],
            ['domain' => 'acme.store', 'user' => 'acme', 'domain_type' => 'parked', 'parent_domain' => 'acme.test'],
            ['domain' => 'other.test', 'user' => 'other', 'domain_type' => 'addon'],
        ]])]);

        self::assertCount(4, Whm::domains()->list());
        self::assertCount(3, Whm::domains()->list('ACME'));

        $main = Whm::domains()->find('Acme.Test.');
        self::assertTrue($main?->isMain());
        self::assertSame('/home/acme/public_html', $main?->documentRoot);
        self::assertSame('ea-php83', $main?->phpVersion);
        self::assertTrue(Whm::domains()->find('shop.acme.test')?->isSubdomain());
        self::assertTrue(Whm::domains()->find('acme.store')?->isAlias());
        self::assertTrue(Whm::domains()->find('other.test')?->isAddon());
        self::assertNull(Whm::domains()->find('missing.test'));
    }

    #[Test]
    public function the_owner_of_a_domain_is_looked_up(): void
    {
        Whm::fake([
            'getdomainowner' => Whm::sequence(Whm::response(['user' => 'acme']), Whm::failure('No user owns the domain.')),
            'domainuserdata' => Whm::response(['userdata' => ['documentroot' => '/home/acme/public_html']]),
        ]);

        self::assertSame('acme', Whm::domains()->owner('acme.test'));
        self::assertNull(Whm::domains()->owner('nobody.test'));
        self::assertSame('/home/acme/public_html', Whm::domains()->userData('acme.test')['documentroot']);
    }

    #[Test]
    public function subdomains_and_aliases_are_created_and_deleted(): void
    {
        $fake = Whm::fake([
            'create_subdomain' => Whm::response(['username' => 'acme']),
            'accountsummary' => Whm::response(['acct' => [['user' => 'acme', 'domain' => 'acme.test']]]),
            'create_parked_domain_for_user' => Whm::response(),
            'delete_domain' => Whm::response(['type' => 'sub', 'username' => 'acme']),
        ]);

        self::assertSame('acme', Whm::domains()->createSubdomain('blog.acme.test', 'public_html/blog'));
        Whm::domains()->createAlias('acme', 'acme.store');
        Whm::domains()->createAlias('acme', 'acme.shop', 'blog.acme.test');
        self::assertSame('sub', Whm::domains()->delete('blog.acme.test'));

        $fake->assertCalled('create_subdomain', static fn (array $p): bool => $p === ['domain' => 'blog.acme.test', 'document_root' => 'public_html/blog', 'use_canonical_name' => 0])
            ->assertCalled('create_parked_domain_for_user', static fn (array $p): bool => $p === ['username' => 'acme', 'domain' => 'acme.store', 'web_vhost_domain' => 'acme.test'])
            ->assertCalled('create_parked_domain_for_user', static fn (array $p): bool => $p['web_vhost_domain'] === 'blog.acme.test')
            ->assertCalledTimes('accountsummary', 1);
    }

    #[Test]
    public function disk_usage_is_converted_to_bytes(): void
    {
        $fake = Whm::fake(['get_disk_usage' => Whm::response(['accounts' => [
            ['user' => 'acme', 'blocks_used' => 1024, 'blocks_limit' => 2048, 'inodes_used' => 340, 'inodes_limit' => 0],
            ['user' => 'free', 'blocks_used' => 10, 'blocks_limit' => null, 'inodes_used' => 1, 'inodes_limit' => null],
        ]])]);

        $acme = Whm::usage()->diskFor('acme');
        self::assertSame(1_048_576, $acme?->usedBytes);
        self::assertSame(2_097_152, $acme?->limitBytes);
        self::assertSame(50.0, $acme?->percentUsed());
        self::assertSame(1.0, $acme?->usedMegabytes());
        self::assertNull($acme?->inodesLimit);
        self::assertTrue(Whm::usage()->diskFor('free')?->isUnlimited());
        self::assertNull(Whm::usage()->diskFor('free')?->percentUsed());

        Whm::usage()->disk(fresh: true);
        $fake->assertCalled('get_disk_usage', static fn (array $p): bool => $p === ['cache_mode' => 'on'])
            ->assertCalled('get_disk_usage', static fn (array $p): bool => $p === ['cache_mode' => 'off']);
    }

    #[Test]
    public function bandwidth_is_read_per_account_and_month(): void
    {
        $fake = Whm::fake(['showbw' => Whm::response([
            'month' => 9,
            'year' => 2026,
            'acct' => [[
                'user' => 'acme', 'maindomain' => 'acme.test', 'owner' => 'root', 'totalbytes' => 524_288_000, 'limit' => 1_048_576_000,
                'bwusage' => [['domain' => 'acme.test', 'usage' => 500_000_000], ['domain' => 'shop.acme.test', 'usage' => 24_288_000]],
            ]],
        ])]);

        $usage = Whm::usage()->bandwidthFor('acme', 9, 2026);

        self::assertSame(500.0, $usage?->usedMegabytes());
        self::assertSame(50.0, $usage?->percentUsed());
        self::assertSame(['acme.test' => 500_000_000, 'shop.acme.test' => 24_288_000], $usage?->byDomain);
        self::assertSame([9, 2026], [$usage?->month, $usage?->year]);
        self::assertFalse($usage?->isUnlimited());
        self::assertCount(1, Whm::usage()->bandwidth(reseller: 'res1'));

        $fake->assertCalled('showbw', static fn (array $p): bool => $p === ['month' => 9, 'year' => 2026, 'searchtype' => 'user', 'search' => '^acme$'])
            ->assertCalled('showbw', static fn (array $p): bool => $p === ['showres' => 'res1']);
    }

    #[Test]
    public function account_usage_combines_disk_and_bandwidth(): void
    {
        Whm::fake([
            'get_disk_usage' => Whm::response(['accounts' => [['user' => 'acme', 'blocks_used' => 950, 'blocks_limit' => 1000]]]),
            'showbw' => Whm::response(['acct' => [['user' => 'acme', 'totalbytes' => 10, 'limit' => 0]]]),
        ]);

        $usage = Whm::usage()->account('acme');

        self::assertSame('acme', $usage->user);
        self::assertTrue($usage->isNearLimit());
        self::assertFalse($usage->isNearLimit(99));
        self::assertTrue($usage->bandwidth?->isUnlimited());
    }

    #[Test]
    public function server_load_services_and_partitions_are_read(): void
    {
        $fake = Whm::fake([
            'systemloadavg' => Whm::response(['one' => 0.17, 'five' => '0.18', 'fifteen' => 0.19]),
            'servicestatus' => Whm::response(['service' => [
                ['name' => 'httpd', 'display_name' => 'Apache', 'enabled' => 1, 'installed' => 1, 'running' => 1, 'monitored' => 1],
                ['name' => 'exim', 'enabled' => 1, 'installed' => 1, 'running' => 0, 'monitored' => 1],
            ]]),
            'restartservice' => Whm::response(['service' => 'exim']),
            'getdiskusage' => Whm::response(['partition' => [
                ['mount' => '/', 'device' => '/dev/vda1', 'total' => 1000, 'used' => 250, 'available' => 750, 'percentage' => 25, 'inodes_ipercentage' => 2],
            ]]),
        ]);

        $load = Whm::server()->loadAverage();
        self::assertSame([0.17, 0.18, 0.19], [$load->one, $load->five, $load->fifteen]);

        $services = Whm::server()->services();
        self::assertSame('Apache', $services[0]->displayName);
        self::assertSame('exim', $services[1]->displayName);
        self::assertTrue($services[1]->isDown());
        self::assertFalse($services[0]->isDown());

        Whm::server()->restartService('exim', queue: true);

        $partition = Whm::server()->partitions()->first();
        self::assertSame(1_024_000, $partition?->totalBytes);
        self::assertSame(25, $partition?->percentUsed);

        $fake->assertCalled('restartservice', static fn (array $p): bool => $p === ['service' => 'exim', 'queue_task' => 1]);
    }
}
