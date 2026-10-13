<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature\Modules;

use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Tests\TestCase;
use Itxshakil\CpanelWhm\WhmRequest;
use PHPUnit\Framework\Attributes\Test;

final class ProvisioningHelpersTest extends TestCase
{
    #[Test]
    public function the_default_nameservers_are_read(): void
    {
        Whm::fake(['get_nameserver_config' => Whm::response(['nameservers' => ['ns1.example.com', '', 'ns2.example.com']])]);

        self::assertSame(['ns1.example.com', 'ns2.example.com'], Whm::dns()->nameservers());
    }

    #[Test]
    public function account_limits_count_against_the_licence(): void
    {
        Whm::fake([
            'get_current_users_count' => Whm::response(['users' => 98]),
            'get_maximum_users' => Whm::response(['users' => 100]),
        ]);

        $limits = Whm::server()->accountLimits();

        self::assertSame(98, $limits->current);
        self::assertSame(100, $limits->maximum);
        self::assertSame(2, $limits->remaining());
        self::assertFalse($limits->isFull());
    }

    #[Test]
    public function an_unlimited_licence_has_no_maximum(): void
    {
        Whm::fake([
            'get_current_users_count' => Whm::response(['users' => 4000]),
            'get_maximum_users' => Whm::response(['users' => 0]),
        ]);

        $limits = Whm::server()->accountLimits();

        self::assertNull($limits->maximum);
        self::assertNull($limits->remaining());
        self::assertFalse($limits->isFull());
    }

    #[Test]
    public function subdomains_are_listed_added_and_deleted(): void
    {
        $fake = Whm::fake(['uapi_cpanel' => static fn (WhmRequest $request) => $request->params['cpanel.function'] === 'listsubdomains'
            ? Whm::uapi([[
                'domain' => 'shop.acme.example', 'subdomain' => 'shop', 'rootdomain' => 'acme.example',
                'dir' => '/home/acme/public_html/shop', 'basedir' => 'public_html/shop',
            ]])
            : Whm::uapi()]);

        $subdomain = Whm::asUser('acme')->subdomains()->list()->sole();
        Whm::asUser('acme')->subdomains()->add('blog', 'acme.example', 'public_html/blog');
        Whm::asUser('acme')->subdomains()->delete('shop.acme.example');

        self::assertSame('shop.acme.example', $subdomain->domain);
        self::assertSame('shop', $subdomain->label);
        self::assertSame('acme.example', $subdomain->rootDomain);
        self::assertSame('/home/acme/public_html/shop', $subdomain->documentRoot);

        $fake->assertCalled('uapi_cpanel', static fn (array $p): bool => $p['cpanel.function'] === 'addsubdomain'
            && $p['domain'] === 'blog' && $p['rootdomain'] === 'acme.example' && $p['dir'] === 'public_html/blog')
            ->assertCalled('uapi_cpanel', static fn (array $p): bool => $p['cpanel.function'] === 'delsubdomain' && $p['domain'] === 'shop.acme.example');
    }

    #[Test]
    public function the_accounts_backups_are_listed_and_one_is_started(): void
    {
        $fake = Whm::fake(['uapi_cpanel' => static fn (WhmRequest $request) => $request->params['cpanel.function'] === 'list_backups'
            ? Whm::uapi(['2026-10-02T00:00:00.000Z', '2026-09-25T00:00:00.000Z', '2026-10-02T00:00:00.000Z'])
            : Whm::uapi(['pid' => '2857'])]);

        self::assertSame(['2026-09-25', '2026-10-02'], Whm::asUser('acme')->backups()->list()->all());
        self::assertSame('2857', Whm::asUser('acme')->backups()->toHomeDirectory('ops@acme.example', includeHomeDirectory: false));

        $fake->assertCalled('uapi_cpanel', static fn (array $p): bool => $p['cpanel.function'] === 'fullbackup_to_homedir'
            && $p['email'] === 'ops@acme.example' && $p['homedir'] === 'skip');
    }
}
