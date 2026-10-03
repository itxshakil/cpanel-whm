<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Enums;

use InvalidArgumentException;

/**
 * The service a create_user_session login link opens.
 */
enum SessionService: string
{
    case Cpanel = 'cpaneld';
    case Whm = 'whostmgrd';
    case Webmail = 'webmaild';

    public static function fromName(string $name): self
    {
        return match (mb_strtolower($name)) {
            'cpanel', 'cpaneld' => self::Cpanel,
            'whm', 'whostmgrd' => self::Whm,
            'webmail', 'webmaild' => self::Webmail,
            default => throw new InvalidArgumentException("Unknown session service [{$name}]. Use cpanel, whm or webmail."),
        };
    }
}
