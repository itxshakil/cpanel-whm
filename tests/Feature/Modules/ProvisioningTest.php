<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature\Modules;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Itxshakil\CpanelWhm\Data\PackageDefinition;
use Itxshakil\CpanelWhm\Exceptions\WhmCommandFailed;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ProvisioningTest extends TestCase
{
    #[Test]
    public function packages_are_created_updated_and_deleted(): void
    {
        $fake = Whm::fake([
            'addpkg' => Whm::response(['pkg' => 'res1_starter']),
            '*' => Whm::response(),
        ]);

        $name = Whm::packages()->create(new PackageDefinition(
            name: 'starter',
            diskQuotaMb: 10_240,
            bandwidthMb: PackageDefinition::UNLIMITED,
            maxEmailAccounts: 10,
            maxEmailsPerHour: 200,
            shell: false,
            dedicatedIp: false,
        ));
        Whm::packages()->update(new PackageDefinition('res1_starter', maxEmailsPerHour: 300, extra: ['digestauth' => 1]));
        Whm::packages()->delete('res1_starter');

        self::assertSame('res1_starter', $name);
        $fake->assertCalled('addpkg', static fn (array $p): bool => $p === [
            'name' => 'starter', 'quota' => 10_240, 'bwlimit' => 'unlimited', 'maxpop' => 10, 'MAX_EMAIL_PER_HOUR' => 200, 'hasshell' => 0, 'ip' => 'n',
        ])
            ->assertCalled('editpkg', static fn (array $p): bool => $p === ['name' => 'res1_starter', 'max_email_per_hour' => 300, 'digestauth' => 1])
            ->assertCalled('killpkg', static fn (array $p): bool => $p === ['pkgname' => 'res1_starter']);
    }

    #[Test]
    public function package_info_reads_the_full_settings(): void
    {
        Whm::fake(['getpkginfo' => Whm::sequence(
            Whm::response(['pkg' => ['QUOTA' => 100, 'MAXPOP' => 20, 'FEATURELIST' => 'default', 'IP' => 1]]),
            Whm::failure('The package “missing” does not exist.'),
            Whm::failure('Access denied'),
        )]);

        $package = Whm::packages()->info('starter');
        self::assertSame('starter', $package?->name);
        self::assertSame('100', $package?->diskQuota);
        self::assertTrue($package?->dedicatedIp);
        self::assertNull(Whm::packages()->info('missing'));

        $this->expectException(WhmCommandFailed::class);
        Whm::packages()->info('secret');
    }

    #[Test]
    public function a_package_read_back_keeps_every_setting_when_saved_elsewhere(): void
    {
        $fake = Whm::fake([
            'getpkginfo' => Whm::response(['pkg' => [
                'QUOTA' => 10_240, 'BWLIMIT' => 'unlimited', 'MAXADDON' => 2, 'MAXPARK' => 5, 'MAXSUB' => 'unlimited',
                'MAXPOP' => 20, 'MAX_EMAILACCT_QUOTA' => 1024, 'MAXSQL' => 10, 'MAXFTP' => 3, 'MAXLST' => 0,
                'MAX_EMAIL_PER_HOUR' => 300, 'FEATURELIST' => 'default', 'CPMOD' => 'jupiter', 'LANG' => 'en',
                'HASSHELL' => 0, 'CGI' => 1, 'IP' => 0,
            ]]),
            'addpkg' => Whm::response(['pkg' => 'starter']),
        ]);

        $package = Whm::packages()->info('starter');

        self::assertSame(['5', '0', '300', '1024', 'jupiter', 'en'], [
            $package?->maxAliases, $package?->maxMailingLists, $package?->maxEmailsPerHour,
            $package?->maxEmailQuota, $package?->theme, $package?->language,
        ]);
        self::assertFalse($package?->shell);
        self::assertTrue($package?->cgi);

        self::assertNotNull($package);
        Whm::packages()->create($package->toDefinition());

        $fake->assertCalled('addpkg', static fn (array $p): bool => $p === [
            'name' => 'starter', 'quota' => '10240', 'bwlimit' => 'unlimited', 'maxaddon' => '2', 'maxpark' => '5',
            'maxsub' => 'unlimited', 'maxpop' => '20', 'max_emailacct_quota' => '1024', 'maxsql' => '10', 'maxftp' => '3',
            'maxlst' => '0', 'MAX_EMAIL_PER_HOUR' => '300', 'featurelist' => 'default', 'cpmod' => 'jupiter',
            'language' => 'en', 'hasshell' => 0, 'cgi' => 1, 'ip' => 'n',
        ]);
    }

    #[Test]
    public function a_package_without_shell_or_cgi_settings_reports_them_as_unknown(): void
    {
        Whm::fake(['listpkgs' => Whm::response(['pkg' => [['name' => 'starter', 'QUOTA' => 100]]])]);

        $package = Whm::packages()->list()->first();

        self::assertNull($package?->shell);
        self::assertNull($package?->cgi);
    }

    #[Test]
    public function autossl_is_run_and_its_problems_read(): void
    {
        $fake = Whm::fake([
            'start_autossl_check_for_one_user' => Whm::response(['pid' => 12345]),
            'get_autossl_problems_for_user' => Whm::response(['problems_by_domain' => [
                ['domain' => 'shop.acme.test', 'problem' => 'shop.acme.test does not resolve to any IP addresses', 'time' => '2026-10-02T03:51:01Z'],
                ['domain' => 'x.acme.test', 'problem' => 'DCV failed', 'time' => 'not a date'],
            ]]),
        ]);

        self::assertSame(12345, Whm::ssl()->runAutoSsl('acme'));

        $problems = Whm::ssl()->autoSslProblems('acme');
        self::assertSame('shop.acme.test', $problems[0]->domain);
        self::assertSame('2026-10-02', $problems[0]->time?->toDateString());
        self::assertNull($problems[1]->time);

        $fake->assertCalled('start_autossl_check_for_one_user', static fn (array $p): bool => $p === ['username' => 'acme']);
    }

    #[Test]
    public function a_certificate_is_installed_with_the_key_in_the_post_body(): void
    {
        $fake = Whm::fake(['installssl' => Whm::response([
            'domain' => 'acme.test', 'user' => 'acme', 'ip' => '203.0.113.10',
            'working_domains' => ['acme.test', 'www.acme.test'], 'warning_domains' => ['mail.acme.test'],
            'message' => 'The SSL certificate is now installed onto the domain “acme.test”.',
        ])]);

        $installed = Whm::ssl()->install('acme.test', '-----BEGIN CERTIFICATE-----', '-----BEGIN PRIVATE KEY-----');

        self::assertSame(['acme.test', 'www.acme.test'], $installed->workingDomains);
        self::assertSame(['mail.acme.test'], $installed->warningDomains);
        self::assertSame('acme', $installed->user);
        $fake->assertCalled('installssl', static fn (array $p, $r): bool => $r->method->value === 'POST' && ! isset($p['cab']) && $r->redactedParams()['key'] === '[REDACTED]' && $p['key'] === '-----BEGIN PRIVATE KEY-----');
    }

    #[Test]
    public function a_certificate_install_that_installssl_reports_as_failed_throws(): void
    {
        Whm::fake(['installssl' => Whm::sequence(
            Whm::response(['status' => 0, 'statusmsg' => 'The certificate does not match the key.']),
            Whm::response(['status' => 1, 'statusmsg' => 'Installed.', 'domain' => 'acme.test']),
        )]);

        try {
            Whm::ssl()->install('acme.test', '-----BEGIN CERTIFICATE-----', '-----BEGIN PRIVATE KEY-----');
            self::fail('Expected WhmCommandFailed');
        } catch (WhmCommandFailed $whmCommandFailed) {
            self::assertSame('installssl', $whmCommandFailed->function());
            self::assertSame('The certificate does not match the key.', $whmCommandFailed->reason());
        }

        self::assertSame('Installed.', Whm::ssl()->install('acme.test', '-----BEGIN CERTIFICATE-----', '-----BEGIN PRIVATE KEY-----')->message);
    }

    #[Test]
    public function api_tokens_are_listed_with_their_expiry(): void
    {
        CarbonImmutable::setTestNow('2026-10-03 12:00:00');

        Whm::fake(['api_token_list' => Whm::response(['tokens' => [
            'billing' => ['name' => 'billing', 'create_time' => 1767225600, 'expires_at' => CarbonImmutable::parse('2026-10-10')->getTimestamp(), 'acls' => ['create-acct' => 1, 'kill-acct' => 0], 'whitelist_ips' => ['203.0.113.0/24']],
            'admin' => ['create_time' => 1767225600, 'expires_at' => null, 'acls' => ['all']],
            'old' => ['create_time' => 1700000000, 'expires_at' => 1700086400, 'acls' => []],
        ]])]);

        $tokens = Whm::tokens()->list();

        self::assertSame(['admin', 'billing', 'old'], $tokens->pluck('name')->all());
        self::assertSame(['all'], $tokens[0]->privileges);
        self::assertFalse($tokens[0]->expires());
        self::assertNull($tokens[0]->daysLeft());
        self::assertSame(['create-acct'], $tokens[1]->privileges);
        self::assertSame(['203.0.113.0/24'], $tokens[1]->allowedIps);
        self::assertSame(6, $tokens[1]->daysLeft());
        self::assertTrue($tokens[2]->isExpired());
        // "old" has already expired: it isn't the token in use, so it isn't "expiring".
        self::assertSame(['billing'], Whm::tokens()->expiringWithin(14)->pluck('name')->all());
        self::assertSame([], Whm::tokens()->expiringWithin(3)->pluck('name')->all());
        self::assertSame('billing', Whm::tokens()->find('billing')?->name);

        CarbonImmutable::setTestNow();
    }

    #[Test]
    public function api_tokens_are_created_and_revoked(): void
    {
        $fake = Whm::fake([
            'api_token_create' => Whm::response(['name' => 'billing', 'token' => 'NEW-SECRET', 'create_time' => 1767225600, 'expires_at' => 1798761600, 'acls' => ['create-acct', 'suspend-acct']]),
            'api_token_revoke' => Whm::response(),
        ]);

        $created = Whm::tokens()->create('billing', ['create-acct', 'suspend-acct'], CarbonImmutable::createFromTimestampUTC(1798761600), ['203.0.113.10']);
        Whm::tokens()->revoke('old', 'older');

        self::assertSame('NEW-SECRET', $created->token);
        self::assertSame(['create-acct', 'suspend-acct'], $created->details->privileges);
        self::assertStringNotContainsString('NEW-SECRET', print_r($created, true));

        $fake->assertCalled('api_token_create', static fn (array $p): bool => $p === [
            'token_name' => 'billing', 'expires_at' => 1798761600, 'acl-0' => 'create-acct', 'acl-1' => 'suspend-acct', 'whitelist_ip-0' => '203.0.113.10',
        ])->assertCalled('api_token_revoke', static fn (array $p): bool => $p === ['token_name' => 'old', 'token_name-1' => 'older']);
    }

    #[Test]
    public function token_names_are_checked_before_sending(): void
    {
        $fake = Whm::fake();

        try {
            Whm::tokens()->create('no spaces allowed');
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException) {
            $fake->assertNothingSent();
        }
    }

    #[Test]
    public function an_empty_privilege_or_ip_list_never_creates_an_unrestricted_token(): void
    {
        $fake = Whm::fake();

        foreach ([['privileges' => []], ['allowedIps' => []]] as $arguments) {
            try {
                Whm::tokens()->create('billing', ...$arguments);
                self::fail('Expected InvalidArgumentException for '.array_key_first($arguments));
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertStringContainsString('Pass null', $invalidArgumentException->getMessage());
            }
        }

        $fake->assertNothingSent();
    }
}
