<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Support;

use DateTimeZone;
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

    /**
     * curl's public key pin format: one or more sha256//<base64 of the SPKI hash>, separated by ";".
     */
    private const string PIN_PATTERN = '#^sha256//[A-Za-z0-9+/]{43}=(?:;sha256//[A-Za-z0-9+/]{43}=)*$#';

    /**
     * @param  string|null  $caBundle  a CA file to verify the certificate against, instead of the system's
     * @param  string|null  $pinnedPublicKey  sha256//... pins; the certificate's public key must match one
     * @param  string|null  $timezone  the server's timezone, for the calendar day of backup dates
     */
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
        public int $retries = 2,
        public int $retryDelayMs = 250,
        public ?string $caBundle = null,
        public ?string $pinnedPublicKey = null,
        public ?string $timezone = null,
        public bool $allowInsecureHttp = false,
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
            'retries' => $this->retries,
            'retryDelayMs' => $this->retryDelayMs,
            'caBundle' => $this->caBundle,
            'pinnedPublicKey' => $this->pinnedPublicKey,
            'timezone' => $this->timezone,
            'allowInsecureHttp' => $this->allowInsecureHttp,
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

        $caBundle = self::string($config['ca_bundle'] ?? null);

        if ($caBundle !== '' && ! is_file($caBundle)) {
            throw InvalidConfiguration::invalidTls($name, "has a CA bundle [{$caBundle}] that doesn't exist.", 'Point WHM_CA_BUNDLE at a PEM file with the CA (or the self-signed certificate) WHM presents.');
        }

        $pin = self::string($config['pinned_public_key'] ?? null);

        if ($pin !== '' && preg_match(self::PIN_PATTERN, $pin) !== 1) {
            throw InvalidConfiguration::invalidTls($name, 'has a pinned public key in the wrong format.', 'Use sha256//<base64>, as php artisan whm:doctor prints it; separate several with ";".');
        }

        if ($pin !== '' && ! defined('CURLOPT_PINNEDPUBLICKEY')) {
            throw InvalidConfiguration::invalidTls($name, 'pins a public key, but this PHP has no curl support for pinning.', 'Install the curl extension built against libcurl 7.39 or newer, or remove WHM_PINNED_PUBLIC_KEY.');
        }

        $timezone = self::string($config['timezone'] ?? null);

        if ($timezone !== '' && ! in_array($timezone, timezone_identifiers_list(DateTimeZone::ALL_WITH_BC), true)) {
            throw InvalidConfiguration::invalidTimezone($name, $timezone);
        }

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
            retries: max(0, self::int(is_array($config['retry'] ?? null) ? ($config['retry']['times'] ?? null) : null, 2)),
            retryDelayMs: max(0, self::int(is_array($config['retry'] ?? null) ? ($config['retry']['sleep_ms'] ?? null) : null, 250)),
            caBundle: $caBundle !== '' ? $caBundle : null,
            pinnedPublicKey: $pin !== '' ? $pin : null,
            timezone: $timezone !== '' ? $timezone : null,
            allowInsecureHttp: (bool) ($config['allow_insecure_http'] ?? false),
        );
    }

    /**
     * A placeholder connection for Whm::fake() when the app has no WHM config.
     */
    public static function fake(string $name): self
    {
        return new self($name, 'https', 'whm.test', 2087, 'root', 'fake-token');
    }

    /**
     * The same connection without the pause between retries. Fakes use it, so
     * tests of retry behaviour do not sleep.
     */
    public function withoutRetryDelay(): self
    {
        return $this->with(retryDelayMs: 0);
    }

    /**
     * The same connection with other timeouts, in seconds (at least 1).
     */
    public function withTimeouts(int $timeout, ?int $connectTimeout = null): self
    {
        return $this->with(timeout: max(1, $timeout), connectTimeout: max(1, $connectTimeout ?? $this->connectTimeout));
    }

    /**
     * The same connection with no retries: one attempt per call.
     */
    public function withoutRetries(): self
    {
        return $this->with(retries: 0);
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
        return filter_var($this->bareHost(), FILTER_VALIDATE_IP) !== false;
    }

    /**
     * The host without the brackets an IPv6 address carries in a URL
     * ([2001:db8::1] becomes 2001:db8::1), for DNS lookups and TLS peer names.
     */
    public function bareHost(): string
    {
        return str_starts_with($this->host, '[') && str_ends_with($this->host, ']')
            ? substr($this->host, 1, -1)
            : $this->host;
    }

    /**
     * A copy with some properties changed. Every copy goes through here, so a
     * new property only has to be carried over in one place.
     */
    private function with(?int $timeout = null, ?int $connectTimeout = null, ?int $retries = null, ?int $retryDelayMs = null): self
    {
        return new self(
            name: $this->name,
            scheme: $this->scheme,
            host: $this->host,
            port: $this->port,
            user: $this->user,
            token: $this->token,
            verifyTls: $this->verifyTls,
            timeout: $timeout ?? $this->timeout,
            connectTimeout: $connectTimeout ?? $this->connectTimeout,
            retries: $retries ?? $this->retries,
            retryDelayMs: $retryDelayMs ?? $this->retryDelayMs,
            caBundle: $this->caBundle,
            pinnedPublicKey: $this->pinnedPublicKey,
            timezone: $this->timezone,
            allowInsecureHttp: $this->allowInsecureHttp,
        );
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
