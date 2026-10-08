<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature\Modules;

use Illuminate\Support\Facades\Event;
use Itxshakil\CpanelWhm\Data\Account;
use Itxshakil\CpanelWhm\Data\NewAccount;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Enums\SearchType;
use Itxshakil\CpanelWhm\Events\AccountCreated;
use Itxshakil\CpanelWhm\Exceptions\InvalidUsername;
use Itxshakil\CpanelWhm\Exceptions\WhmCommandFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmPermissionDenied;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Modules\Accounts;
use Itxshakil\CpanelWhm\Support\Filter;
use Itxshakil\CpanelWhm\Tests\Fixtures\Responses;
use Itxshakil\CpanelWhm\Tests\TestCase;
use Itxshakil\CpanelWhm\WhmRequest;
use PHPUnit\Framework\Attributes\Test;

final class AccountsTest extends TestCase
{
    #[Test]
    public function create_posts_with_a_long_timeout_and_returns_what_whm_assigned(): void
    {
        $fake = Whm::fake(['createacct' => Whm::response(
            ['user' => 'acme1', 'ip' => '203.0.113.20', 'package' => 'starter', 'nameserver' => 'ns1.example.com', 'nameserver2' => 'ns2.example.com'],
            'Account Creation Ok',
            ['output' => ['raw' => 'Account Creation Complete!!!']],
        )]);

        $account = Whm::accounts()->create(new NewAccount(
            username: 'Acme',
            domain: 'Acme.Example',
            package: 'starter',
            password: 'S3cure!pass',
            contactEmail: 'ops@acme.example',
        ));

        self::assertSame('acme1', $account->username);
        self::assertSame('acme.example', $account->domain);
        self::assertSame('203.0.113.20', $account->ip);
        self::assertSame(['ns1.example.com', 'ns2.example.com'], $account->nameservers);
        self::assertSame('Account Creation Complete!!!', $account->rawOutput);

        $fake->assertCalled('createacct', static fn (array $params, WhmRequest $request): bool => $request->method === HttpMethod::Post
            && $request->timeout === 120
            && $params === [
                'username' => 'acme',
                'domain' => 'acme.example',
                'plan' => 'starter',
                'password' => 'S3cure!pass',
                'contactemail' => 'ops@acme.example',
            ]);
    }

    #[Test]
    public function without_a_password_a_strong_one_is_generated_sent_and_returned(): void
    {
        $fake = Whm::fake(['createacct' => Whm::response(['user' => 'acme'])]);

        $account = Whm::accounts()->create(new NewAccount('acme', 'acme.example'));

        self::assertIsString($account->password);
        self::assertSame(24, strlen($account->password));
        self::assertMatchesRegularExpression('/[a-z]/', $account->password);
        self::assertMatchesRegularExpression('/[A-Z]/', $account->password);
        self::assertMatchesRegularExpression('/\d/', $account->password);
        self::assertMatchesRegularExpression('/[^a-zA-Z\d]/', $account->password);
        $fake->assertCalled('createacct', static fn (array $params): bool => $params['password'] === $account->password);

        self::assertNotSame($account->password, Whm::accounts()->create(new NewAccount('acme', 'acme.example'))->password);
    }

    #[Test]
    public function a_given_password_is_returned_and_never_replaced(): void
    {
        $fake = Whm::fake(['createacct' => Whm::response(['user' => 'acme'])]);

        self::assertSame('S3cure!pass', Whm::accounts()->create(new NewAccount('acme', 'acme.example', password: 'S3cure!pass'))->password);
        self::assertSame('From-extra-1', Whm::accounts()->create(new NewAccount('acme', 'acme.example', extra: ['password' => 'From-extra-1']))->password);

        $fake->assertCalled('createacct', static fn (array $params): bool => $params['password'] === 'From-extra-1');
    }

    #[Test]
    public function the_created_password_stays_out_of_dumps_and_the_event(): void
    {
        Event::fake([AccountCreated::class]);
        Whm::fake(['createacct' => Whm::response(['user' => 'acme'])]);

        $account = Whm::accounts()->create(new NewAccount('acme', 'acme.example', password: 'S3cure!pass'));

        self::assertStringNotContainsString('S3cure!pass', print_r($account, true));
        Event::assertDispatched(AccountCreated::class, static fn (AccountCreated $event): bool => $event->account->password === null
            && $event->account->username === 'acme');
    }

    #[Test]
    public function an_invalid_username_is_refused_before_calling_whm(): void
    {
        $fake = Whm::fake();

        try {
            Whm::accounts()->create(new NewAccount('test-site'));
            self::fail('Expected InvalidUsername');
        } catch (InvalidUsername) {
        }

        $fake->assertNothingSent();
    }

    #[Test]
    public function a_new_account_keeps_its_password_out_of_dumps(): void
    {
        $dump = print_r(new NewAccount('acme', password: 'hunter2'), true);

        self::assertStringNotContainsString('hunter2', $dump);
    }

    #[Test]
    public function accounts_are_listed_as_typed_objects(): void
    {
        Whm::fake(['listaccts' => Whm::response(['acct' => [
            Responses::account(),
            Responses::account(['user' => 'bob', 'domain' => 'bob.example', 'suspended' => 1, 'suspendreason' => 'Unpaid', 'email' => '*unknown*']),
        ]])]);

        $accounts = Whm::accounts()->list();

        self::assertCount(2, $accounts);
        self::assertInstanceOf(Account::class, $accounts[0]);
        self::assertSame('starter', $accounts[0]->package);
        self::assertFalse($accounts[0]->suspended);
        self::assertNull($accounts[0]->suspendReason);
        self::assertSame('2026-01-01', $accounts[0]->createdAt?->toDateString());
        self::assertTrue($accounts[1]->isSuspended());
        self::assertSame('Unpaid', $accounts[1]->suspendReason);
        self::assertNull($accounts[1]->email);
    }

    #[Test]
    public function filters_and_searches_are_passed_to_listaccts(): void
    {
        $fake = Whm::fake(['listaccts' => Whm::response(['acct' => []])]);

        Whm::accounts()->list(Filter::where('domain', 'contains', 'acme'));
        Whm::accounts()->search('acme.example', SearchType::Domain, exact: true);
        Whm::accounts()->search('acme.co');

        $fake->assertCalled('listaccts', static fn (array $params): bool => ($params['api.filter.a.arg0'] ?? null) === 'acme')
            ->assertCalled('listaccts', static fn (array $params): bool => $params === ['searchtype' => 'domain', 'search' => 'acme.example', 'searchmethod' => 'exact'])
            ->assertCalled('listaccts', static fn (array $params): bool => $params === ['searchtype' => 'user', 'search' => 'acme\.co', 'searchmethod' => 'regex']);
    }

    #[Test]
    public function find_returns_null_when_the_account_does_not_exist(): void
    {
        Whm::fake(['accountsummary' => Whm::sequence(
            Whm::response(['acct' => [Responses::account()]]),
            Whm::failure('Account does not exist.'),
            Whm::failure('Something else broke.'),
        )]);

        self::assertSame('acme.example', Whm::accounts()->find('ACME')?->domain);
        self::assertFalse(Whm::accounts()->exists('ghost'));

        $this->expectException(WhmCommandFailed::class);
        $this->expectExceptionMessage('Something else broke.');

        Whm::accounts()->find('broken');
    }

    #[Test]
    public function username_availability_checks_local_rules_then_whm(): void
    {
        $fake = Whm::fake(['verify_new_username' => Whm::sequence(Whm::response(), Whm::failure('This username is taken.'))]);

        self::assertFalse(Whm::accounts()->isUsernameAvailable('root'));
        self::assertTrue(Whm::accounts()->isUsernameAvailable('acme'));
        self::assertFalse(Whm::accounts()->isUsernameAvailable('taken'));

        $fake->assertCalledTimes('verify_new_username', 2);
    }

    #[Test]
    public function a_token_without_the_privilege_is_not_reported_as_a_taken_name(): void
    {
        Whm::fake(['verify_new_username' => Whm::failure('Access denied')]);

        $this->expectException(WhmPermissionDenied::class);

        Whm::accounts()->isUsernameAvailable('acme');
    }

    #[Test]
    public function extra_parameters_cannot_replace_the_validated_username(): void
    {
        $fake = Whm::fake(['createacct' => Whm::response(['user' => 'acme']), 'modifyacct' => Whm::response()]);

        Whm::accounts()->create(new NewAccount('acme', 'acme.example', extra: ['username' => 'Root;', 'maxftp' => 5]));
        Whm::accounts()->modify('acme', ['user' => 'other', 'CONTACTEMAIL' => 'a@acme.example']);

        $fake->assertCalled('createacct', static fn (array $params): bool => $params['username'] === 'acme' && $params['maxftp'] === 5);
        $fake->assertCalled('modifyacct', static fn (array $params): bool => $params['user'] === 'acme' && $params['CONTACTEMAIL'] === 'a@acme.example');
    }

    #[Test]
    public function accounts_can_be_removed_repassworded_repackaged_and_modified(): void
    {
        $fake = Whm::fake(['*' => Whm::response()]);

        Whm::accounts()->remove('acme', keepDns: true);
        Whm::accounts()->changePassword('acme', 'n3w-Pass', syncDatabasePasswords: true);
        Whm::accounts()->changePackage('acme', 'pro');
        Whm::accounts()->modify('acme', ['CONTACTEMAIL' => 'new@acme.example']);

        $fake->assertCalled('removeacct', static fn (array $p, WhmRequest $r): bool => $p === ['user' => 'acme', 'keepdns' => 1] && $r->method === HttpMethod::Post)
            ->assertCalled('passwd', static fn (array $p, WhmRequest $r): bool => $p['db_pass_update'] === 1 && $r->method === HttpMethod::Post)
            ->assertCalled('changepackage', static fn (array $p): bool => $p === ['user' => 'acme', 'pkg' => 'pro'])
            ->assertCalled('modifyacct', static fn (array $p): bool => $p === ['user' => 'acme', 'CONTACTEMAIL' => 'new@acme.example']);
    }

    #[Test]
    public function modules_are_macroable(): void
    {
        Whm::fake(['listaccts' => Whm::response(['acct' => [Responses::account(), Responses::account(['user' => 'bob', 'owner' => 'reseller1'])]])]);

        Accounts::macro('ownedBy', function (string $owner) {
            /** @var Accounts $this */
            return $this->list()->where('owner', $owner)->values();
        });

        self::assertSame(['bob'], Whm::accounts()->ownedBy('reseller1')->pluck('username')->all());
    }
}
