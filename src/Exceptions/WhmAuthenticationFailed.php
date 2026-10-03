<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Exceptions;

/**
 * WHM rejected the API token (HTTP 401 or 403).
 */
final class WhmAuthenticationFailed extends WhmHttpError
{
    public function hint(): string
    {
        return 'The token was rejected: it may be mistyped, expired or revoked, or belong to another user. Create one in WHM > Development > Manage API Tokens and update WHM_TOKEN and WHM_USER.';
    }
}
