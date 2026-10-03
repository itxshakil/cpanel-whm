<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Facades;

use Illuminate\Support\Facades\Facade;
use Itxshakil\CpanelWhm\Testing\FakeConnectionFailure;
use Itxshakil\CpanelWhm\Testing\FakeSequence;
use Itxshakil\CpanelWhm\Testing\WhmFake;
use Itxshakil\CpanelWhm\Transport\TransportResponse;
use Itxshakil\CpanelWhm\WhmManager;

/**
 * @method static \Itxshakil\CpanelWhm\Contracts\WhmClient connection(?string $name = null)
 * @method static \Itxshakil\CpanelWhm\WhmResponse call(string $function, array<string, mixed> $params = [], \Itxshakil\CpanelWhm\Enums\HttpMethod $method = \Itxshakil\CpanelWhm\Enums\HttpMethod::Get, ?int $timeout = null)
 * @method static \Itxshakil\CpanelWhm\Modules\Accounts accounts()
 * @method static \Itxshakil\CpanelWhm\Modules\Suspensions suspensions()
 * @method static \Itxshakil\CpanelWhm\Modules\Packages packages()
 * @method static \Itxshakil\CpanelWhm\Modules\Quotas quotas()
 * @method static \Itxshakil\CpanelWhm\Modules\Sessions sessions()
 * @method static \Itxshakil\CpanelWhm\Modules\Server server()
 * @method static \Itxshakil\CpanelWhm\Modules\Dns dns()
 * @method static \Itxshakil\CpanelWhm\Modules\Domains domains()
 * @method static \Itxshakil\CpanelWhm\Modules\Usage usage()
 * @method static \Itxshakil\CpanelWhm\Modules\Backups backups()
 * @method static \Itxshakil\CpanelWhm\Modules\Resellers resellers()
 * @method static \Itxshakil\CpanelWhm\Modules\Ssl ssl()
 * @method static \Itxshakil\CpanelWhm\Modules\Tokens tokens()
 * @method static \Itxshakil\CpanelWhm\Api\WhmApi api()
 * @method static \Itxshakil\CpanelWhm\Contracts\WhmClient cache(int|\DateInterval|\DateTimeInterface $ttl, ?string $store = null)
 * @method static \Itxshakil\CpanelWhm\Modules\CpanelUser asUser(string $user)
 * @method static \Itxshakil\CpanelWhm\Support\ConnectionConfig config()
 * @method static \Itxshakil\CpanelWhm\Support\ConnectionConfig configFor(string $name)
 * @method static \Itxshakil\CpanelWhm\Doctor\ConnectionReport ping(?string $connection = null)
 * @method static string getDefaultConnection()
 * @method static list<string> connectionNames()
 * @method static bool isFaked()
 * @method static void purge(?string $name = null)
 *
 * @see WhmManager
 */
final class Whm extends Facade
{
    /**
     * Fake every WHM call. Responses are keyed by function name ("*" for any).
     *
     * @param  array<string, mixed>  $responses
     */
    public static function fake(array $responses = []): WhmFake
    {
        /** @var WhmManager $manager */
        $manager = self::getFacadeRoot();

        return $manager->fake($responses);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  array<string, mixed>  $metadata
     */
    public static function response(array $data = [], string $reason = 'OK', array $metadata = []): TransportResponse
    {
        return WhmFake::response($data, $reason, $metadata);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function failure(string $reason, array $data = []): TransportResponse
    {
        return WhmFake::failure($reason, $data);
    }

    public static function httpError(int $status = 500, string $body = ''): TransportResponse
    {
        return WhmFake::httpError($status, $body);
    }

    public static function connectionError(string $message = 'Connection refused'): FakeConnectionFailure
    {
        return WhmFake::connectionError($message);
    }

    /**
     * @param  list<string>  $warnings
     * @param  list<string>  $messages
     */
    public static function uapi(mixed $data = [], array $warnings = [], array $messages = []): TransportResponse
    {
        return WhmFake::uapi($data, $warnings, $messages);
    }

    /**
     * @param  string|list<string>  $errors
     */
    public static function uapiFailure(string|array $errors): TransportResponse
    {
        return WhmFake::uapiFailure($errors);
    }

    public static function sequence(mixed ...$responses): FakeSequence
    {
        return WhmFake::sequence(...$responses);
    }

    /**
     * A response saved with php artisan whm:record.
     */
    public static function fixture(string $path): TransportResponse
    {
        return WhmFake::fixture($path);
    }

    protected static function getFacadeAccessor(): string
    {
        return WhmManager::class;
    }
}
