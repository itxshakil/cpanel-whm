<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature\Modules;

use InvalidArgumentException;
use Itxshakil\CpanelWhm\Data\EmailAccount;
use Itxshakil\CpanelWhm\Exceptions\UapiCallFailed;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Tests\TestCase;
use Itxshakil\CpanelWhm\WhmRequest;
use PHPUnit\Framework\Attributes\Test;

final class UserEmailAndMysqlTest extends TestCase
{
    #[Test]
    public function mailboxes_are_listed_as_typed_objects(): void
    {
        $fake = Whm::fake(['uapi_cpanel' => Whm::uapi([
            [
                'email' => 'info@acme.example', 'user' => 'info', 'domain' => 'acme.example', 'login' => 'info@acme.example',
                '_diskused' => 52_428_800, '_diskquota' => 104_857_600, 'diskusedpercent_float' => 50.0,
                'suspended_login' => 0, 'suspended_incoming' => 1, 'suspended_outgoing' => 0, 'hold_outgoing' => 0, 'mtime' => 1_790_000_000,
            ],
            ['email' => 'Main Account', 'user' => 'acme', 'login' => 'acme', '_diskused' => 1, '_diskquota' => 0],
            ['email' => 'sales@acme.example', 'user' => 'sales', 'domain' => 'acme.example', '_diskused' => 10, '_diskquota' => 0],
        ])]);

        $mailboxes = Whm::asUser('acme')->email()->list();

        self::assertCount(2, $mailboxes);
        $info = $mailboxes->first();
        self::assertInstanceOf(EmailAccount::class, $info);
        self::assertSame('info@acme.example', $info->address);
        self::assertSame('info', $info->user);
        self::assertSame('acme.example', $info->domain);
        self::assertSame(52_428_800, $info->usedBytes);
        self::assertSame(104_857_600, $info->quotaBytes);
        self::assertSame(50.0, $info->percentUsed());
        self::assertFalse($info->loginSuspended);
        self::assertTrue($info->incomingSuspended);
        self::assertSame(1_790_000_000, $info->modifiedAt?->getTimestamp());
        self::assertNull($mailboxes->last()?->quotaBytes);
        self::assertNull($mailboxes->last()?->percentUsed());

        $fake->assertCalled('uapi_cpanel', static fn (array $p): bool => $p['cpanel.user'] === 'acme'
            && $p['cpanel.module'] === 'Email' && $p['cpanel.function'] === 'list_pops_with_disk');
    }

    #[Test]
    public function a_mailbox_is_created_with_a_generated_password_when_none_is_given(): void
    {
        $fake = Whm::fake(['uapi_cpanel' => Whm::uapi('info+acme.example')]);

        $created = Whm::asUser('acme')->email()->create('Info@Acme.Example', quotaMegabytes: 1024);

        self::assertSame('info@acme.example', $created->address);
        self::assertSame(24, strlen((string) $created->password));
        self::assertStringNotContainsString((string) $created->password, print_r($created, true));

        $fake->assertCalled('uapi_cpanel', static fn (array $p): bool => $p['cpanel.function'] === 'add_pop'
            && $p['email'] === 'info' && $p['domain'] === 'acme.example' && $p['password'] === $created->password
            && $p['quota'] === 1024 && $p['send_welcome_email'] === 0);
    }

    #[Test]
    public function mailbox_changes_send_the_local_part_and_domain(): void
    {
        $fake = Whm::fake(['uapi_cpanel' => Whm::uapi()]);
        $email = Whm::asUser('acme')->email();

        $email->changePassword('info@acme.example', 'N3w-Secret!');
        $email->setQuota('info@acme.example', null);
        $email->setQuota('info@acme.example', 2048);
        $email->suspendLogin('info@acme.example');
        $email->unsuspendLogin('info@acme.example');
        $email->suspendIncoming('info@acme.example');
        $email->unsuspendIncoming('info@acme.example');
        $email->delete('info@acme.example');

        $calls = array_map(static fn (WhmRequest $r): array => [$r->params['cpanel.function'], array_diff_key($r->params, array_flip(['cpanel.user', 'cpanel.module', 'cpanel.function']))], $fake->recorded());

        self::assertSame([
            ['passwd_pop', ['email' => 'info', 'password' => 'N3w-Secret!', 'domain' => 'acme.example']],
            ['edit_pop_quota', ['domain' => 'acme.example', 'email' => 'info', 'quota' => 'unlimited']],
            ['edit_pop_quota', ['domain' => 'acme.example', 'email' => 'info', 'quota' => '2048']],
            ['suspend_login', ['email' => 'info@acme.example']],
            ['unsuspend_login', ['email' => 'info@acme.example']],
            ['suspend_incoming', ['email' => 'info@acme.example']],
            ['unsuspend_incoming', ['email' => 'info@acme.example']],
            ['delete_pop', ['email' => 'info', 'domain' => 'acme.example']],
        ], $calls);
    }

    #[Test]
    public function forwarders_are_listed_added_and_removed(): void
    {
        $fake = Whm::fake(['uapi_cpanel' => static fn (WhmRequest $r) => $r->params['cpanel.function'] === 'list_forwarders'
            ? Whm::uapi([['forward' => 'info@acme.example', 'dest' => 'ops@example.com']])
            : Whm::uapi()]);
        $email = Whm::asUser('acme')->email();

        $forwarders = $email->forwarders('acme.example');
        $email->addForwarder('info@acme.example', 'ops@example.com');
        $email->deleteForwarder('info@acme.example', 'ops@example.com');

        self::assertSame('info@acme.example', $forwarders->first()?->address);
        self::assertSame('ops@example.com', $forwarders->first()?->destination);
        $fake->assertCalled('uapi_cpanel', static fn (array $p): bool => $p['cpanel.function'] === 'add_forwarder'
            && $p['domain'] === 'acme.example' && $p['email'] === 'info' && $p['fwdopt'] === 'fwd' && $p['fwdemail'] === 'ops@example.com')
            ->assertCalled('uapi_cpanel', static fn (array $p): bool => $p['cpanel.function'] === 'delete_forwarder'
                && $p['address'] === 'info@acme.example' && $p['forwarder'] === 'ops@example.com');
    }

    #[Test]
    public function an_address_without_a_domain_is_refused_before_calling_whm(): void
    {
        $fake = Whm::fake();

        try {
            Whm::asUser('acme')->email()->delete('info');
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringContainsString('info@example.com', $invalidArgumentException->getMessage());
        }

        $fake->assertNothingSent();
    }

    #[Test]
    public function uapi_failures_surface_from_the_helpers(): void
    {
        Whm::fake(['uapi_cpanel' => Whm::uapiFailure('The account info@acme.example already exists!')]);

        $this->expectException(UapiCallFailed::class);
        $this->expectExceptionMessage('already exists');

        Whm::asUser('acme')->email()->create('info@acme.example', 'S3cure-pass!');
    }

    #[Test]
    public function databases_and_users_are_listed_as_typed_objects(): void
    {
        Whm::fake(['uapi_cpanel' => static fn (WhmRequest $r) => match ($r->params['cpanel.function']) {
            'list_databases' => Whm::uapi([['database' => 'acme_shop', 'disk_usage' => 2048, 'users' => ['acme_app']]]),
            'list_users' => Whm::uapi([['user' => 'acme_app', 'shortuser' => 'app', 'databases' => ['acme_shop']]]),
            default => Whm::uapi(),
        }]);
        $mysql = Whm::asUser('acme')->mysql();

        $database = $mysql->databases()->first();
        $user = $mysql->users()->first();

        self::assertSame('acme_shop', $database?->name);
        self::assertSame(2048, $database?->diskUsageBytes);
        self::assertSame(['acme_app'], $database?->users);
        self::assertSame('acme_app', $user?->name);
        self::assertSame('app', $user?->shortName);
        self::assertSame(['acme_shop'], $user?->databases);
    }

    #[Test]
    public function databases_users_and_grants_are_managed(): void
    {
        $fake = Whm::fake(['uapi_cpanel' => Whm::uapi()]);
        $mysql = Whm::asUser('acme')->mysql();

        $mysql->createDatabase('acme_shop');
        $created = $mysql->createUser('acme_app');
        $mysql->grant('acme_app', 'acme_shop');
        $mysql->grant('acme_app', 'acme_shop', ['SELECT', 'INSERT']);
        $mysql->changePassword('acme_app', 'An0ther-Secret');
        $mysql->revoke('acme_app', 'acme_shop');
        $mysql->deleteUser('acme_app');
        $mysql->deleteDatabase('acme_shop');

        self::assertSame('acme_app', $created->name);
        self::assertSame(24, strlen((string) $created->password));
        self::assertStringNotContainsString((string) $created->password, print_r($created, true));

        $calls = array_map(static fn (WhmRequest $r): array => [$r->params['cpanel.function'], array_diff_key($r->params, array_flip(['cpanel.user', 'cpanel.module', 'cpanel.function']))], $fake->recorded());

        self::assertSame([
            ['create_database', ['name' => 'acme_shop']],
            ['create_user', ['name' => 'acme_app', 'password' => $created->password]],
            ['set_privileges_on_database', ['database' => 'acme_shop', 'user' => 'acme_app', 'privileges' => 'ALL PRIVILEGES']],
            ['set_privileges_on_database', ['database' => 'acme_shop', 'user' => 'acme_app', 'privileges' => 'SELECT,INSERT']],
            ['set_password', ['password' => 'An0ther-Secret', 'user' => 'acme_app']],
            ['revoke_access_to_database', ['database' => 'acme_shop', 'user' => 'acme_app']],
            ['delete_user', ['name' => 'acme_app']],
            ['delete_database', ['name' => 'acme_shop']],
        ], $calls);
    }
}
