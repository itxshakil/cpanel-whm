<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Exceptions;

use Itxshakil\CpanelWhm\Data\UapiResult;

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
}
