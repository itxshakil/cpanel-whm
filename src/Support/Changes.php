<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Support;

use Itxshakil\CpanelWhm\Data\Value;

/**
 * Whether a call changes something on the server, which decides when
 * WhmFunctionSucceeded fires.
 *
 * uapi_cpanel and cpanel (with apiversion 3) run a UAPI function inside, so
 * the answer comes from that function's catalog entry: Email::list_pops is a
 * read, Email::add_pop a change. A function the catalog doesn't know counts
 * as a change, as FunctionCatalog::isReadOnly() treats it.
 *
 * @internal
 */
final class Changes
{
    /**
     * @param  array<string, mixed>  $params  the normalised parameters of the call
     */
    public static function made(string $function, array $params): bool
    {
        $uapi = self::uapiFunction($function, $params);

        if ($uapi !== null) {
            return ! (FunctionCatalog::uapi($uapi[0], $uapi[1])['readOnly'] ?? false);
        }

        return ! FunctionCatalog::isReadOnly($function);
    }

    /**
     * The UAPI module and function a uapi_cpanel or cpanel call runs, or null.
     *
     * @param  array<string, mixed>  $params
     * @return array{0: string, 1: string}|null
     */
    public static function uapiFunction(string $function, array $params): ?array
    {
        [$moduleKey, $functionKey] = match (true) {
            $function === 'uapi_cpanel' => ['cpanel.module', 'cpanel.function'],
            $function === 'cpanel' && Value::string($params['cpanel_jsonapi_apiversion'] ?? null) === '3' => ['cpanel_jsonapi_module', 'cpanel_jsonapi_func'],
            default => [null, null],
        };

        if ($moduleKey === null || $functionKey === null) {
            return null;
        }

        $module = Value::string($params[$moduleKey] ?? null);
        $name = Value::string($params[$functionKey] ?? null);

        return $module !== null && $name !== null ? [$module, $name] : null;
    }
}
