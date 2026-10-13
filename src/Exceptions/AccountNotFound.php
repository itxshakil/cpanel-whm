<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Exceptions;

/**
 * WHM says the cPanel account the call names doesn't exist. Catch it to treat
 * "already gone" differently from a real failure, e.g. in a retried job.
 */
final class AccountNotFound extends WhmCommandFailed
{
    public function hint(): string
    {
        return 'Check the username: WHM has no account by that name, or it was removed or renamed. Accounts::find() returns null for a missing account instead of throwing.';
    }
}
