<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Exceptions;

/**
 * A username broke a cPanel rule, caught locally before any request was sent.
 */
final class InvalidUsername extends WhmException
{
    public function __construct(
        private readonly string $username,
        private readonly string $rule,
    ) {
        parent::__construct("Invalid cPanel username [{$username}]: {$rule}");
    }

    public function username(): string
    {
        return $this->username;
    }

    public function rule(): string
    {
        return $this->rule;
    }
}
