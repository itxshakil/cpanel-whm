<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature\Modules;

use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Events\AccountModified;
use Itxshakil\CpanelWhm\Exceptions\InvalidUsername;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Tests\TestCase;
use Itxshakil\CpanelWhm\WhmRequest;
use PHPUnit\Framework\Attributes\Test;

final class FeatureListsAndAccountChangesTest extends TestCase
{
    #[Test]
    public function feature_lists_are_listed_and_read(): void
    {
        Whm::fake([
            'get_featurelists' => Whm::response(['featurelists' => ['default', 'disabled', 'basic']]),
            'get_featurelist_data' => Whm::response(['featurelist' => 'basic', 'features' => [
                ['id' => 'autossl', 'name' => 'AutoSSL', 'value' => 1, 'is_disabled' => 0],
                ['id' => 'changemx', 'name' => 'Change MX', 'value' => 0, 'is_disabled' => 0],
            ]]),
        ]);

        self::assertSame(['default', 'disabled', 'basic'], Whm::featureLists()->list());
        self::assertSame(['autossl' => true, 'changemx' => false], Whm::featureLists()->features('basic'));
    }

    #[Test]
    public function feature_lists_are_created_updated_and_deleted(): void
    {
        $fake = Whm::fake(['*' => Whm::response()]);

        Whm::featureLists()->create('basic', ['autossl' => true, 'changemx' => false]);
        Whm::featureLists()->update('basic', ['changemx' => true]);
        Whm::featureLists()->delete('basic');

        $fake->assertCalled('create_featurelist', static fn (array $p, WhmRequest $r): bool => $p === ['autossl' => 1, 'changemx' => 0, 'featurelist' => 'basic', 'overwrite' => 0]
            && $r->method === HttpMethod::Post)
            ->assertCalled('update_featurelist', static fn (array $p): bool => $p === ['changemx' => 1, 'featurelist' => 'basic'])
            ->assertCalled('delete_featurelist', static fn (array $p): bool => $p === ['featurelist' => 'basic']);
    }

    #[Test]
    public function an_update_without_features_is_refused_because_whm_would_turn_them_all_off(): void
    {
        $fake = Whm::fake();

        try {
            Whm::featureLists()->update('basic', []);
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException) {
            $fake->assertNothingSent();
        }
    }

    #[Test]
    public function the_primary_domain_ip_and_username_can_be_changed(): void
    {
        Event::fake([AccountModified::class]);
        $fake = Whm::fake(['*' => Whm::response()]);

        Whm::accounts()->changePrimaryDomain('Acme', 'New-Acme.Example.');
        Whm::accounts()->changeIp('acme', ' 203.0.113.40 ');
        Whm::accounts()->rename('acme', 'Acme2');

        $fake->assertCalled('modifyacct', static fn (array $p): bool => $p === ['user' => 'acme', 'DNS' => 'new-acme.example'])
            ->assertCalled('setsiteip', static fn (array $p, WhmRequest $r): bool => $p === ['user' => 'acme', 'ip' => '203.0.113.40'] && $r->method === HttpMethod::Post)
            ->assertCalled('modifyacct', static fn (array $p): bool => $p === ['user' => 'acme', 'newuser' => 'acme2']);

        Event::assertDispatched(AccountModified::class, static fn (AccountModified $e): bool => $e->changes === ['ip' => '203.0.113.40']);
        Event::assertDispatchedTimes(AccountModified::class, 3);
    }

    #[Test]
    public function an_invalid_new_username_is_refused_before_calling_whm(): void
    {
        $fake = Whm::fake();

        try {
            Whm::accounts()->rename('acme', 'test-account');
            self::fail('Expected InvalidUsername');
        } catch (InvalidUsername) {
            $fake->assertNothingSent();
        }
    }
}
