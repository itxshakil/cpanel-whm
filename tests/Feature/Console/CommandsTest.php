<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature\Console;

use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Tests\Fixtures\Responses;
use Itxshakil\CpanelWhm\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class CommandsTest extends TestCase
{
    #[Test]
    public function call_runs_any_function_and_prints_rows_as_a_table(): void
    {
        $fake = Whm::fake(['listaccts' => Whm::response(['acct' => [Responses::account()]])]);

        $this->artisan('whm:call', ['function' => 'listaccts', 'params' => ['searchtype=user', 'search=acme']])
            ->expectsOutputToContain('acme.example')
            ->expectsOutputToContain('listaccts: OK')
            ->assertSuccessful();

        $fake->assertCalled('listaccts', static fn (array $p): bool => $p === ['searchtype' => 'user', 'search' => 'acme']);
    }

    #[Test]
    public function call_prints_key_value_pairs_and_warnings_for_non_list_data(): void
    {
        Whm::fake(['modifyacct' => Whm::response(['acct' => ['user' => 'acme']], metadata: ['output' => ['warnings' => ['You cannot change backup settings.']]])]);

        $this->artisan('whm:call', ['function' => 'modifyacct', 'params' => ['user=acme', 'BACKUP=1'], '--post' => true])
            ->expectsOutputToContain('acct.user')
            ->expectsOutputToContain('You cannot change backup settings.')
            ->assertSuccessful();
    }

    #[Test]
    public function call_prints_redacted_json(): void
    {
        Whm::fake(['create_user_session' => Whm::response(['url' => 'https://h:2083/cpsess123/login/?session=acme:x'])]);

        $this->artisan('whm:call', ['function' => 'create_user_session', 'params' => ['user=acme', 'service=cpaneld'], '--json' => true])
            ->expectsOutputToContain('cpsess[REDACTED]')
            ->doesntExpectOutputToContain('cpsess123')
            ->assertSuccessful();
    }

    #[Test]
    public function a_dry_run_sends_nothing_and_prints_no_secrets(): void
    {
        $fake = Whm::fake();
        config()->set('cpanel-whm.connections', ['main' => ['host' => 'server.example.com', 'token' => 'SECRET-TOKEN-123']]);
        Whm::purge();

        $this->artisan('whm:call', ['function' => 'passwd', 'params' => ['user=acme', 'password=hunter2'], '--dry-run' => true])
            ->expectsOutputToContain('GET https://server.example.com:2087/json-api/passwd?api.version=1&user=acme&password=%5BREDACTED%5D')
            ->expectsOutputToContain('Authorization: whm root:[REDACTED]')
            ->doesntExpectOutputToContain('SECRET-TOKEN-123')
            ->assertSuccessful();

        $fake->assertNothingSent();
    }

    #[Test]
    public function destructive_functions_need_the_name_typed_to_run(): void
    {
        $fake = Whm::fake(['removeacct' => Whm::response()]);

        $this->artisan('whm:call', ['function' => 'removeacct', 'params' => ['user=acme']])
            ->expectsQuestion('Type removeacct to confirm', 'no')
            ->assertFailed();

        $fake->assertNotCalled('removeacct');

        $this->artisan('whm:call', ['function' => 'removeacct', 'params' => ['user=acme']])
            ->expectsQuestion('Type removeacct to confirm', 'removeacct')
            ->assertSuccessful();

        $fake->assertCalled('removeacct');
    }

    #[Test]
    public function a_failed_call_prints_the_error_and_its_hint(): void
    {
        Whm::fake(['removeacct' => Whm::failure('Access denied')]);

        $this->artisan('whm:call', ['function' => 'removeacct', '--force' => true])
            ->expectsOutputToContain('WHM removeacct failed: Access denied')
            ->expectsOutputToContain('whm:doctor')
            ->assertFailed();
    }

    #[Test]
    public function call_explains_a_bad_connection(): void
    {
        $this->artisan('whm:call', ['function' => 'version', '--connection' => 'nope'])
            ->expectsOutputToContain('WHM connection [nope] is not configured.')
            ->assertFailed();
    }

    #[Test]
    public function functions_lists_typed_methods_and_what_the_server_has_beyond_them(): void
    {
        Whm::fake(['applist' => Whm::response(['app' => ['createacct', 'listips', 'version']])]);

        $this->artisan('whm:functions', ['search' => 'acct'])
            ->expectsOutputToContain('accounts()->create()')
            ->assertSuccessful();

        $this->artisan('whm:functions', ['--server' => true, '--missing' => true])
            ->expectsOutputToContain("Whm::call('listips')")
            ->doesntExpectOutputToContain('accounts()->create()')
            ->expectsOutputToContain('3 functions on this server, 2 with a typed method')
            ->assertSuccessful();
    }

    #[Test]
    public function functions_reports_an_unreachable_server(): void
    {
        Whm::fake(['applist' => Whm::connectionError()]);

        $this->artisan('whm:functions', ['--server' => true])->assertFailed();
    }

    #[Test]
    public function accounts_can_be_filtered_to_suspended_ones(): void
    {
        Whm::fake(['listaccts' => Whm::response(['acct' => [
            Responses::account(),
            Responses::account(['user' => 'bob', 'domain' => 'bob.example', 'suspended' => 1, 'suspendreason' => 'Unpaid']),
        ]])]);

        $this->artisan('whm:accounts', ['--suspended' => true])
            ->expectsOutputToContain('bob.example')
            ->doesntExpectOutputToContain('acme.example')
            ->expectsOutputToContain('1 account(s).')
            ->assertSuccessful();
    }

    #[Test]
    public function accounts_can_be_searched_by_package_and_by_any_field(): void
    {
        $fake = Whm::fake(['listaccts' => Whm::response(['acct' => []])]);

        $this->artisan('whm:accounts', ['--package' => 'starter'])
            ->expectsOutputToContain('No accounts found.')
            ->assertSuccessful();

        $this->artisan('whm:accounts', ['--search' => 'acme', '--by' => 'domain'])->assertSuccessful();

        $fake->assertCalled('listaccts', static fn (array $p): bool => $p === ['searchtype' => 'package', 'search' => 'starter', 'searchmethod' => 'exact'])
            ->assertCalled('listaccts', static fn (array $p): bool => $p['searchtype'] === 'domain');
    }

    #[Test]
    public function accounts_rejects_an_unknown_search_field(): void
    {
        Whm::fake();

        $this->artisan('whm:accounts', ['--search' => 'x', '--by' => 'colour'])->assertFailed();
    }

    #[Test]
    public function account_shows_one_account_or_says_it_does_not_exist(): void
    {
        Whm::fake(['accountsummary' => Whm::sequence(
            Whm::response(['acct' => [Responses::account(['suspended' => 1, 'suspendreason' => 'Unpaid'])]]),
            Whm::failure('Account does not exist.'),
        )]);

        $this->artisan('whm:account', ['user' => 'acme'])
            ->expectsOutputToContain('acme.example')
            ->assertSuccessful();

        $this->artisan('whm:account', ['user' => 'ghost'])
            ->expectsOutputToContain('Account [ghost] does not exist.')
            ->assertFailed();
    }

    #[Test]
    public function login_prints_a_link(): void
    {
        $fake = Whm::fake(['create_user_session' => Whm::response(['url' => 'https://server.example.com:2083/cpsess1/login/?session=acme:x'])]);

        $this->artisan('whm:login', ['user' => 'acme', '--service' => 'webmail', '--app' => 'Email_Accounts'])
            ->expectsOutputToContain('https://server.example.com:2083/cpsess1/login/')
            ->assertSuccessful();

        $fake->assertCalled('create_user_session', static fn (array $p): bool => $p['service'] === 'webmaild');
    }

    #[Test]
    public function login_rejects_an_unknown_service(): void
    {
        Whm::fake();

        $this->artisan('whm:login', ['user' => 'acme', '--service' => 'ftp'])->assertFailed();
    }
}
