<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Enums;

/**
 * The listaccts "searchtype" values.
 */
enum SearchType: string
{
    case Domain = 'domain';
    case Owner = 'owner';
    case User = 'user';
    case Ip = 'ip';
    case Package = 'package';
}
