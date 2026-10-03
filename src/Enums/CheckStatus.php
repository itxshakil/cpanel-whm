<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Enums;

enum CheckStatus: string
{
    case Pass = 'pass';
    case Warn = 'warn';
    case Fail = 'fail';
    case Skip = 'skip';

    public function symbol(): string
    {
        return match ($this) {
            self::Pass => '✔',
            self::Warn => '!',
            self::Fail => '✖',
            self::Skip => '·',
        };
    }
}
