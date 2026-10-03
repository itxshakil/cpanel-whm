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
        self::assertSame(['billing', 'old'], Whm::tokens()->expiringWithin(14)->pluck('name')->all());
        self::assertSame(['old'], Whm::tokens()->expiringWithin(3)->pluck('name')->all());
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
}
