<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Exceptions;

use Itxshakil\CpanelWhm\Data\UapiResult;
use Itxshakil\CpanelWhm\Data\Value;
use Itxshakil\CpanelWhm\WhmResponse;

/**
 * The uapi_cpanel bridge worked, but the UAPI function itself failed (status 0).
 */
final class UapiCallFailed extends WhmException
{
    public function __construct(
        private readonly string $module,
        private readonly string $function,
        private readonly UapiResult $result,
    ) {
        $errors = $result->errors !== [] ? implode(' ', $result->errors) : 'no error message';

        parent::__construct("UAPI {$module}::{$function} failed: {$errors}");
    }

    /**
     * uapi_cpanel and cpanel run a cPanel function inside a WHM call. WHM
     * reports success when it ran the function, even if the function itself
     * failed, so the inner status is read here. Null when it succeeded or the
     * call is not one of those two.
     *
     * @param  array<string, mixed>  $params  the parameters the WHM function was called with
     */
    public static function fromInnerCall(string $whmFunction, array $params, WhmResponse $response): ?self
    {
        $keys = match ($whmFunction) {
            'uapi_cpanel' => ['cpanel.module', 'cpanel.function', ['uapi']],
            // UAPI answers under result, cPanel API 2 under cpanelresult (event.result).
            'cpanel' => ['cpanel_jsonapi_module', 'cpanel_jsonapi_func', ['uapi', 'result', 'cpanelresult']],
            default => null,
        };

        if ($keys === null) {
            return null;
        }

        [$moduleKey, $functionKey, $candidates] = $keys;

        foreach ($candidates as $path) {
            $inner = $response->get($path);

            if (! is_array($inner)) {
                continue;
            }

            $status = $inner['status'] ?? data_get($inner, 'event.result');

            if ($status === null || Value::bool($status)) {
                continue;
            }

            $result = UapiResult::fromArray($inner);

            if ($result->errors === [] && is_string($inner['error'] ?? null) && $inner['error'] !== '') {
                $result = new UapiResult(false, $result->data, [$inner['error']], $result->warnings, $result->messages, $result->metadata);
            }

            return new self(
                Value::string($params[$moduleKey] ?? null) ?? '',
                Value::string($params[$functionKey] ?? null) ?? '',
                $result,
            );
        }

        return null;
    }

    public function module(): string
    {
        return $this->module;
    }

    public function function(): string
    {
        return $this->function;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->result->errors;
    }

    public function result(): UapiResult
    {
        return $this->result;
    }

    public function redacted(): static
    {
        return new self($this->module, $this->function, $this->result->redacted());
    }
}
