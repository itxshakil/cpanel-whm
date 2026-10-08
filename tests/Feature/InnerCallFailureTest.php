<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature;

use Itxshakil\CpanelWhm\Exceptions\UapiCallFailed;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * WHM says "OK" when it ran a cPanel function, even if that function failed.
 */
final class InnerCallFailureTest extends TestCase
{
    #[Test]
    public function a_raw_uapi_cpanel_call_throws_when_the_uapi_function_failed(): void
    {
        Whm::fake(['uapi_cpanel' => Whm::uapiFailure('The email account already exists.')]);

        try {
            Whm::call('uapi_cpanel', ['cpanel.user' => 'acme', 'cpanel.module' => 'Email', 'cpanel.function' => 'add_pop']);
            self::fail('Expected UapiCallFailed');
        } catch (UapiCallFailed $uapiCallFailed) {
            self::assertSame('Email', $uapiCallFailed->module());
            self::assertSame('add_pop', $uapiCallFailed->function());
            self::assertSame(['The email account already exists.'], $uapiCallFailed->errors());
        }
    }

    #[Test]
    public function the_cpanel_function_throws_when_the_uapi_function_failed(): void
    {
        Whm::fake(['cpanel' => Whm::response(['result' => ['status' => 0, 'errors' => ['Database exists.'], 'data' => null]])]);

        $this->expectException(UapiCallFailed::class);
        $this->expectExceptionMessage('UAPI Mysql::create_database failed: Database exists.');

        Whm::api()->apiDevelopmentTools()->cpanel('create_database', 'Mysql', 'acme', 3, ['name' => 'acme_shop']);
    }

    #[Test]
    public function the_cpanel_function_throws_when_a_cpanel_api_2_function_failed(): void
    {
        Whm::fake(['cpanel' => Whm::response(['cpanelresult' => ['event' => ['result' => 0], 'error' => 'Access denied.', 'data' => []]])]);

        $this->expectException(UapiCallFailed::class);
        $this->expectExceptionMessage('Access denied.');

        Whm::api()->apiDevelopmentTools()->cpanel('listpops', 'Email', 'acme', 2);
    }

    #[Test]
    public function a_successful_inner_call_is_returned(): void
    {
        Whm::fake(['cpanel' => Whm::response(['result' => ['status' => 1, 'errors' => null, 'data' => ['ok' => 1]]])]);

        $response = Whm::api()->apiDevelopmentTools()->cpanel('list_databases', 'Mysql', 'acme', 3);

        self::assertSame(1, $response->get('result.data.ok'));
    }
}
