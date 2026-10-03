<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Contracts;

use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Modules\Accounts;
use Itxshakil\CpanelWhm\Modules\CpanelUser;
use Itxshakil\CpanelWhm\Modules\Packages;
use Itxshakil\CpanelWhm\Modules\Quotas;
use Itxshakil\CpanelWhm\Modules\Server;
use Itxshakil\CpanelWhm\Modules\Sessions;
use Itxshakil\CpanelWhm\Modules\Suspensions;
use Itxshakil\CpanelWhm\Support\ConnectionConfig;
use Itxshakil\CpanelWhm\WhmResponse;

/**
 * One WHM server. Inject this interface; Whm::fake() swaps what is behind it.
 */
interface WhmClient
{
    /**
     * Call any WHM API 1 function. Throws on connection errors, HTTP errors and
     * when WHM reports failure (metadata.result = 0).
     *
     * @param  array<string, mixed>  $params
     *
     * @throws WhmException
     */
    public function call(string $function, array $params = [], HttpMethod $method = HttpMethod::Get, ?int $timeout = null): WhmResponse;

    public function accounts(): Accounts;

    public function suspensions(): Suspensions;

    public function packages(): Packages;

    public function quotas(): Quotas;

    public function sessions(): Sessions;

    public function server(): Server;

    /**
     * Run cPanel UAPI functions as this account, through WHM's uapi_cpanel.
     */
    public function asUser(string $user): CpanelUser;

    public function config(): ConnectionConfig;
}
