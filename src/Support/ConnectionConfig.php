<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Support;

use Itxshakil\CpanelWhm\Exceptions\InvalidConfiguration;
use SensitiveParameter;

/**
 * One validated WHM connection. Built from config/cpanel-whm.php.
 */
final readonly class ConnectionConfig
{
    /**
     * Ports that serve cPanel or Webmail. WHM API 1 cannot be called on them.
     */
    public const array CPANEL_PORTS = [2082, 2083, 2095, 2096];

    public function __construct(
        public string $name,
        public string $scheme,
        public string $host,
        public int $port,
        public string $user,
        #[SensitiveParameter]
        public string $token,
        public bool $verifyTls = true,
        public int $timeout = 30,
        public int $connectTimeout = 10,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'name' => $this->name,
            'url' => $this->baseUrl(),
            'user' => $this->user,
            'token' => '[REDACTED]',
            'verifyTls' => $this->verifyTls,
            'timeout' => $this->timeout,
            'connectTimeout' => $this->connectTimeout,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidConfiguration
     */
    public static function fromArray(string $name, array $config): self
    {
        $rawHost = self::string($config['host'] ?? null);

        if ($rawHost === '') {
            throw InvalidConfiguration::missing($name, 'host', 'WHM_HOST');
        }

        $token = self::string($config['token'] ?? null);

        if ($token === '') {
            throw InvalidConfiguration::missing($name, 'token', 'WHM_TOKEN');
        }

        $user = self::string($config['user'] ?? null);

        [$scheme, $host, $port] = self::parseHost($name, $rawHost);

        return new self(
            name: $name,
            scheme: $scheme,
            host: $host,
            port: $port,
            user: $user !== '' ? $user : 'root',
            token: $token,
            verifyTls: (bool) ($config['verify_tls'] ?? true),
            timeout: max(1, self::int($config['timeout'] ?? null, 30)),
            connectTimeout: max(1, self::int($config['connect_timeout'] ?? null, 10)),
        );
    }

    /**
     * A placeholder connection for Whm::fake() when the app has no WHM config.
     */
    public static function fake(string $name): self
    {
        return new self($name, 'https', 'whm.test', 2087, 'root', 'fake-token');
    }

    public function baseUrl(): string
    {
        return "{$this->scheme}://{$this->host}:{$this->port}";
    }

    public function endpoint(string $function): string
    {
        return "{$this->baseUrl()}/json-api/{$function}";
    }

    public function usesTls(): bool
    {
        return $this->scheme === 'https';
    }

    public function isIpAddress(): bool
    {
        return filter_var($this->host, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * @return array{0: string, 1: string, 2: int}
     */
    private static function parseHost(string $name, string $rawHost): array
    {
        $withScheme = preg_match('#^[a-z][a-z0-9+.-]*://#i', $rawHost) === 1 ? $rawHost : "https://{$rawHost}";
        $parts = parse_url($withScheme);

        if (! is_array($parts) || ! isset($parts['host']) || $parts['host'] === '') {
            throw InvalidConfiguration::invalidHost($name, $rawHost);
        }

        $scheme = mb_strtolower($parts['scheme'] ?? 'https');

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw InvalidConfiguration::invalidHost($name, $rawHost);
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 2087 : 2086);

        if (in_array($port, self::CPANEL_PORTS, true)) {
            throw InvalidConfiguration::cpanelPort($name, $port);
        }

        return [$scheme, mb_strtolower($parts['host']), $port];
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private static function int(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }
}
