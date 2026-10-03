<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Exceptions;

final class InvalidConfiguration extends WhmException
{
    private ?string $hintText = null;

    public static function unknownConnection(string $name): self
    {
        $exception = new self("WHM connection [{$name}] is not configured.");
        $exception->hintText = "Add it under 'connections' in config/cpanel-whm.php.";

        return $exception;
    }

    public static function missing(string $connection, string $key, string $env): self
    {
        $exception = new self("WHM connection [{$connection}] has no {$key}.");
        $exception->hintText = "Set {$env} in .env, or run php artisan whm:install.";

        return $exception;
    }

    public static function invalidHost(string $connection, string $host): self
    {
        $exception = new self("WHM connection [{$connection}] has an invalid host [{$host}].");
        $exception->hintText = 'Use a hostname such as server.example.com or a URL such as https://server.example.com:2087.';

        return $exception;
    }

    public static function cpanelPort(string $connection, int $port): self
    {
        $exception = new self("WHM connection [{$connection}] uses port {$port}, which serves cPanel or Webmail, not WHM API 1.");
        $exception->hintText = 'Use port 2087 (or 2086 without TLS).';

        return $exception;
    }

    public function hint(): ?string
    {
        return $this->hintText;
    }
}
