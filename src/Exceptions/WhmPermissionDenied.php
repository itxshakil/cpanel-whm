<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Exceptions;

/**
 * The token is valid but its ACL does not allow this function.
 */
final class WhmPermissionDenied extends WhmCommandFailed
{
    public function hint(): string
    {
        return 'The token is valid but not allowed to run '.$this->function().'. Give the token (or reseller ACL) the needed privilege, then run php artisan whm:doctor to see what it can do.';
    }
}
