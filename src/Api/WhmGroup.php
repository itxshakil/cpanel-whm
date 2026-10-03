<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Api;

use Itxshakil\CpanelWhm\Contracts\WhmClient;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\WhmResponse;

/**
 * Base for the generated WHM API 1 groups in Api\Whm.
 *
 * Null arguments are left out of the request, booleans are sent as 1/0, and a
 * list is sent the way WHM expects repeated values: name, name-1, name-2, ...
 * Functions that change something are sent as POST, so values stay out of URLs.
 */
abstract class WhmGroup
{
    public function __construct(protected readonly WhmClient $client) {}

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $extra
     *
     * @throws WhmException
     */
    protected function invoke(string $function, array $params, array $extra = [], HttpMethod $method = HttpMethod::Get): WhmResponse
    {
        return $this->client->call($function, [...$params, ...$extra], $method);
    }
}
