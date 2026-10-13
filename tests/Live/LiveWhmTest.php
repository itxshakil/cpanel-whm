<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Live;

use Itxshakil\CpanelWhm\Data\NewAccount;
use Itxshakil\CpanelWhm\Exceptions\UapiCallFailed;
use Itxshakil\CpanelWhm\Facades\Whm;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Checks the behaviour the fakes can only assume: the shapes and encodings a
 * real WHM uses. Reads only, unless WHM_LIVE_WRITE=1.
 */
#[Group('live')]
final class LiveWhmTest extends LiveTestCase
{
    #[Test]
    public function the_server_answers_reads_with_the_token(): void
    {
        self::assertMatchesRegularExpression('/^\d+\.\d+/', Whm::server()->version());
        self::assertNotSame('', Whm::server()->hostname());
        self::assertNotSame([], Whm::server()->privileges());

        Whm::accounts()->list();
        $this->addToAssertionCount(1);
    }

    /**
     * RELEASING.md: WHM must accept the repeated command, command-1 parameters,
     * including a command that has its own parameters.
     */
    #[Test]
    public function a_batch_with_parameters_is_accepted(): void
    {
        $results = Whm::batch()
            ->add('version')
            ->add('listaccts', ['searchtype' => 'user', 'search' => 'no-such-user-'.bin2hex(random_bytes(3)), 'searchmethod' => 'exact'])
            ->send();

        self::assertTrue($results->successful(), 'The batch should succeed: '.json_encode(array_map(static fn ($f) => $f->getMessage(), $results->failures())));
        self::assertCount(2, $results);
        $version = $results[0]->get('version');
        self::assertIsString($version);
        self::assertMatchesRegularExpression('/^\d+\.\d+/', $version);
    }

    /**
     * RELEASING.md: cPanel's spec doesn't document how a failed UAPI call
     * inside uapi_cpanel looks; this proves UapiCallFailed reads it.
     */
    #[Test]
    public function a_failed_uapi_call_is_reported_as_uapi_call_failed(): void
    {
        $account = self::requireEnv('WHM_LIVE_ACCOUNT', 'run UAPI checks as an existing account');

        try {
            Whm::asUser($account)->uapi('DomainInfo', 'single_domain_data', ['domain' => 'no-such-domain-'.bin2hex(random_bytes(3)).'.invalid']);
            self::fail('Expected UapiCallFailed');
        } catch (UapiCallFailed $uapiCallFailed) {
            self::assertSame('DomainInfo', $uapiCallFailed->module());
            self::assertNotSame([], $uapiCallFailed->errors());
        }
    }

    #[Test]
    public function an_account_can_be_created_found_suspended_and_removed(): void
    {
        if (self::env('WHM_LIVE_WRITE') !== '1') {
            self::markTestSkipped('Set WHM_LIVE_WRITE=1 (and WHM_LIVE_DOMAIN) to create and remove a throwaway account.');
        }

        $parent = self::requireEnv('WHM_LIVE_DOMAIN', 'pick the domain the throwaway account is created under');
        $username = 'cwlive'.bin2hex(random_bytes(3));

        $created = Whm::accounts()->create(new NewAccount(
            username: $username,
            domain: "{$username}.{$parent}",
            package: self::env('WHM_LIVE_PACKAGE'),
        ));

        try {
            $found = Whm::accounts()->find($created->username);
            self::assertSame("{$username}.{$parent}", $found?->domain);

            Whm::suspensions()->suspend($created->username, 'cpanel-whm live test');
            $suspended = Whm::accounts()->find($created->username);
            self::assertTrue($suspended?->suspended);

            Whm::suspensions()->unsuspend($created->username);
            $unsuspended = Whm::accounts()->find($created->username);
            self::assertFalse($unsuspended?->suspended);
        } finally {
            Whm::accounts()->remove($created->username);
        }

        self::assertNull(Whm::accounts()->find($created->username));
    }
}
