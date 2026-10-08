<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Enums;

use Itxshakil\CpanelWhm\Support\FunctionCatalog;
use Itxshakil\CpanelWhm\Support\Redactor;

enum HttpMethod: string
{
    case Get = 'GET';
    case Post = 'POST';

    /**
     * The method a call is sent with. Secrets always go as POST, so they never
     * end up in a URL, a server access log or a proxy log. Otherwise the
     * requested method wins, then the one cPanel documents for the function.
     * Functions cPanel does not document (plugins, for example) are sent as POST.
     *
     * @param  array<array-key, mixed>  $params
     */
    public static function for(string $function, array $params = [], ?self $requested = null): self
    {
        if (Redactor::containsSensitive($params)) {
            return self::Post;
        }

        if ($requested instanceof self) {
            return $requested;
        }

        $documented = FunctionCatalog::whm($function)['httpMethod'] ?? null;

        return $documented === null ? self::Post : self::from($documented);
    }
}
