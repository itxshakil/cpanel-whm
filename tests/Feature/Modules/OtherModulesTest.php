<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature\Modules;

use InvalidArgumentException;
use Itxshakil\CpanelWhm\Enums\SessionService;
use Itxshakil\CpanelWhm\Exceptions\UapiCallFailed;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Tests\TestCase;
use Itxshakil\CpanelWhm\WhmRequest;
use PHPUnit\Framework\Attributes\Test;

final class OtherModulesTest extends TestCase
{
    #[Test]
    public function accounts_can_be_suspended_locked_and_unsuspended(): void
    {
        $fake = Whm::fake(['*' => Whm::response()]);

        Whm::suspensions()->suspend('acme', 'Unpaid', lock: true);
        Whm::suspensions()->unsuspend('acme');

        $fake->assertCalled('suspendacct', static fn (array $p): bool => $p === ['user' => 'acme', 'reason' => 'Unpaid', 'disallowun' => 1])
            ->assertCalled('unsuspendacct', static fn (array $p): bool => $p === ['user' => 'acme']);
    }

    #[Test]
    public function suspended_accounts_are_listed(): void
    {
        Whm::fake(['listsuspended' => Whm::response(['account' => [
            ['user' => 'acme', 'owner' => 'root', 'reason' => 'Unpaid', 'unixtime' => 1767225600, 'is_locked' => 1],
        ]])]);

        $suspended = Whm::suspensions()->list()->first();

        self::assertSame('acme', $suspended?->username);
        self::assertTrue($suspended?->locked);
        self::assertSame('2026-01-01', $suspended?->suspendedAt?->toDateString());
    }

    #[Test]
    public function packages_are_listed_and_found(): void
    {
        Whm::fake(['listpkgs' => Whm::response(['pkg' => [
            ['name' => 'starter', 'QUOTA' => '10240', 'BWLIMIT' => 'unlimited', 'FEATURELIST' => 'default', 'MAXPOP' => '10', 'IP' => 'n'],
            ['name' => 'pro', 'QUOTA' => 'unlimited', 'IP' => 'y'],
        ]])]);

        self::assertCount(2, Whm::packages()->list());
        self::assertSame('10240', Whm::packages()->find('starter')?->diskQuota);
        self::assertSame('10', Whm::packages()->find('starter')?->maxEmailAccounts);
        self::assertTrue(Whm::packages()->find('pro')?->dedicatedIp);
        self::assertNull(Whm::packages()->find('missing'));
    }

    #[Test]
    public function quotas_treat_null_as_unlimited(): void
    {
        $fake = Whm::fake(['*' => Whm::response()]);

        Whm::quotas()->setDiskQuota('acme', null);
        Whm::quotas()->setBandwidthLimit('acme', null);
        Whm::quotas()->setBandwidthLimit('acme', 5000);

        $fake->assertCalled('editquota', static fn (array $p): bool => $p === ['user' => 'acme', 'quota' => 0])
            ->assertCalled('limitbw', static fn (array $p): bool => $p['bwlimit'] === 'unlimited')
            ->assertCalled('limitbw', static fn (array $p): bool => $p['bwlimit'] === 5000);
    }

    #[Test]
    public function one_time_login_links_are_created(): void
    {
        $fake = Whm::fake(['create_user_session' => Whm::response([
            'url' => 'https://server.example.com:2083/cpsess4567/login/?session=acme:xyz',
            'session' => 'acme:xyz',
            'cp_security_token' => '/cpsess4567',
            'expires' => 1767225600,
        ])]);

        $session = Whm::sessions()->create('ACME', SessionService::Cpanel, 'Email_Accounts');

        self::assertStringContainsString('/login/', $session->url);
        self::assertSame('acme', $session->user);
        self::assertSame('2026-01-01', $session->expiresAt?->toDateString());
        self::assertStringNotContainsString('cpsess4567', print_r($session, true));

        $fake->assertCalled('create_user_session', static fn (array $p): bool => $p === ['user' => 'acme', 'service' => 'cpaneld', 'app' => 'Email_Accounts']);
    }

    #[Test]
    public function session_services_have_friendly_names(): void
    {
        self::assertSame(SessionService::Whm, SessionService::fromName('WHM'));

        $this->expectException(InvalidArgumentException::class);

        SessionService::fromName('ftp');
    }

    #[Test]
    public function server_details_and_privileges_are_read(): void
    {
        Whm::fake([
            'version' => ['version' => '11.134.0.5'],
            'applist' => ['app' => ['createacct', 'listaccts']],
            'myprivs' => Whm::sequence(
                ['privileges' => [['all' => 1]]],
                ['privileges' => [['create-acct' => 1, 'kill-acct' => 0]]],
                ['privileges' => [['all' => 1]]],
                ['privileges' => ['create-acct', 'list-accts']],
                ['privileges' => 'none'],
            ),
            'gethostname' => ['hostname' => 'server.example.com'],
        ]);

        self::assertSame('11.134.0.5', Whm::server()->version());
        self::assertSame(['createacct', 'listaccts'], Whm::server()->functions());
        self::assertSame(['all'], Whm::server()->privileges());
        self::assertSame(['create-acct'], Whm::server()->privileges());
        self::assertTrue(Whm::server()->hasPrivilege('kill-acct'));
        self::assertTrue(Whm::server()->hasPrivilege('list-accts'));
        self::assertSame([], Whm::server()->privileges());
        self::assertSame('server.example.com', Whm::server()->hostname());
    }

    #[Test]
    public function uapi_runs_as_an_account_through_uapi_cpanel(): void
    {
        $fake = Whm::fake(['uapi_cpanel' => Whm::uapi([['email' => 'info@acme.example']], warnings: ['quota nearly full'])]);

        $result = Whm::asUser('Acme')->uapi('Email', 'list_pops', ['regex' => 'info']);

        self::assertTrue($result->successful);
        self::assertSame('info@acme.example', $result->get('0.email'));
        self::assertSame(['quota nearly full'], $result->warnings);
        self::assertCount(1, $result->toArray());
        self::assertSame('acme', Whm::asUser('ACME')->user());

        $fake->assertCalled('uapi_cpanel', static fn (array $p, WhmRequest $r): bool => $p === [
            'cpanel.user' => 'acme',
            'cpanel.module' => 'Email',
            'cpanel.function' => 'list_pops',
            'regex' => 'info',
        ] && $r->method->value === 'POST');
    }

    #[Test]
    public function a_failed_uapi_function_inside_a_whm_success_raises_uapi_call_failed(): void
    {
        Whm::fake(['uapi_cpanel' => Whm::uapiFailure(['The mailbox already exists.', 'Try another name.'])]);

        try {
            Whm::asUser('acme')->uapi('Email', 'add_pop', ['email' => 'info']);
            self::fail('Expected UapiCallFailed');
        } catch (UapiCallFailed $uapiCallFailed) {
            self::assertSame('UAPI Email::add_pop failed: The mailbox already exists. Try another name.', $uapiCallFailed->getMessage());
            self::assertSame(['The mailbox already exists.', 'Try another name.'], $uapiCallFailed->errors());
            self::assertSame('Email', $uapiCallFailed->module());
            self::assertSame('add_pop', $uapiCallFailed->function());
            self::assertFalse($uapiCallFailed->result()->successful);
            self::assertNull($uapiCallFailed->hint());
        }
    }
}
