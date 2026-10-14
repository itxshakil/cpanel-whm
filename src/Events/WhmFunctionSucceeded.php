<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Events;

use Itxshakil\CpanelWhm\Support\Changes;
use Itxshakil\CpanelWhm\WhmResponse;

/**
 * Fired after a call that changes something succeeded, whichever path made
 * it: a module, Whm::api(), Whm::call(), asUser()->uapi(), or a command
 * inside a Whm::batch() (one event per successful command, none for the
 * batch request itself). Reads never fire it.
 *
 * It fires alongside the module events (AccountCreated, DnsZoneChanged, ...),
 * not instead of them, and before them. Use it for an audit log or a sync
 * that must see every change; use the module events for their typed payload.
 *
 * The parameters and the response are redacted copies.
 */
final readonly class WhmFunctionSucceeded
{
    /**
     * @param  string  $connection  the connection name
     * @param  string  $function  the WHM function, such as createacct or uapi_cpanel
     * @param  array<string, mixed>  $params  the normalised, redacted parameters
     */
    public function __construct(
        public string $connection,
        public string $function,
        public array $params,
        public WhmResponse $response,
    ) {}

    /**
     * "Module::function" when the call ran a UAPI function (uapi_cpanel, or
     * cpanel with apiversion 3), such as "Email::add_pop"; otherwise null.
     */
    public function uapi(): ?string
    {
        $uapi = Changes::uapiFunction($this->function, $this->params);

        return $uapi === null ? null : $uapi[0].'::'.$uapi[1];
    }
}
