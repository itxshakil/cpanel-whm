<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use Itxshakil\CpanelWhm\Contracts\WhmClient;
use Itxshakil\CpanelWhm\Data\UapiResult;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Exceptions\UapiCallFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Support\UsernameRules;

/**
 * Runs cPanel UAPI functions as one account through WHM's uapi_cpanel, using
 * the WHM token. No per-account cPanel credentials are needed.
 *
 *     Whm::asUser('acme')->uapi('Email', 'list_pops');
 */
class CpanelUser extends Module
{
    private readonly string $user;

    public function __construct(WhmClient $client, string $user)
    {
        parent::__construct($client);

        $this->user = UsernameRules::normalise($user);
    }

    public function user(): string
    {
        return $this->user;
    }

    /**
     * @param  array<string, mixed>  $params
     *
     * @throws UapiCallFailed when the UAPI function reports failure
     * @throws WhmException
     */
    public function uapi(string $module, string $function, array $params = []): UapiResult
    {
        // POST keeps whatever the function receives (passwords included) out of URLs.
        $response = $this->client->call('uapi_cpanel', [
            'cpanel.user' => $this->user,
            'cpanel.module' => $module,
            'cpanel.function' => $function,
            ...$params,
        ], HttpMethod::Post);

        $uapi = $response->get('uapi');
        $result = UapiResult::fromArray(is_array($uapi) ? $uapi : []);

        if (! $result->successful) {
            throw new UapiCallFailed($module, $function, $result);
        }

        return $result;
    }
}
