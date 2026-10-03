<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Api;

use Itxshakil\CpanelWhm\Data\UapiResult;
use Itxshakil\CpanelWhm\Exceptions\UapiCallFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Modules\CpanelUser;

/**
 * Base for the generated UAPI modules in Api\Uapi. Calls run through WHM's
 * uapi_cpanel as the account the CpanelUser was made for.
 */
abstract class UapiModule
{
    public const string MODULE = '';

    public function __construct(protected readonly CpanelUser $user) {}

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $extra
     *
     * @throws UapiCallFailed
     * @throws WhmException
     */
    protected function invoke(string $function, array $params, array $extra = []): UapiResult
    {
        return $this->user->uapi(static::MODULE, $function, [...$params, ...$extra]);
    }
}
