<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Api;

use Itxshakil\CpanelWhm\Contracts\WhmClient;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\WhmResponse;
use JsonException;

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

    /**
     * For a parameter the spec documents as JSON (mass_edit_dns_zone's add and
     * edit, for example): an array is encoded, and a list gets each of its array
     * items encoded, since WHM reads one JSON string per repeated value. Strings
     * pass through, so already-encoded JSON still works.
     *
     * @throws JsonException
     */
    public static function json(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return array_map(
            static fn (mixed $item): mixed => is_array($item) ? json_encode($item, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $item,
            $value,
        );
    }
}
