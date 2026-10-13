<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Doctor;

use Carbon\CarbonImmutable;
use Closure;
use OpenSSLCertificate;

/**
 * NetworkProbe on PHP streams: DNS lookup, TCP connect and a TLS handshake
 * that captures the server certificate.
 */
final class SocketProbe implements NetworkProbe
{
    /**
     * @param  (Closure(string): list<string>)|null  $lookupIpv4  replaces gethostbynamel() in tests
     * @param  (Closure(string): list<string>)|null  $lookupIpv6  replaces the AAAA lookup in tests
     */
    public function __construct(
        private readonly ?Closure $lookupIpv4 = null,
        private readonly ?Closure $lookupIpv6 = null,
    ) {}

    /**
     * IPv4 and IPv6 addresses. gethostbynamel() alone would fail a server that
     * only has an AAAA record, which cURL reaches fine.
     */
    public function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $ipv4 = $this->lookupIpv4 instanceof Closure ? ($this->lookupIpv4)($host) : self::ipv4($host);
        $ipv6 = $this->lookupIpv6 instanceof Closure ? ($this->lookupIpv6)($host) : self::ipv6($host);

        return array_values(array_unique([...$ipv4, ...$ipv6]));
    }

    /**
     * @return list<string>
     */
    private static function ipv4(string $host): array
    {
        $addresses = gethostbynamel($host);

        return $addresses === false ? [] : $addresses;
    }

    /**
     * @return list<string>
     */
    private static function ipv6(string $host): array
    {
        $records = @dns_get_record($host, DNS_AAAA);

        if (! is_array($records)) {
            return [];
        }

        $addresses = [];

        foreach ($records as $record) {
            if (is_string($record['ipv6'] ?? null)) {
                $addresses[] = $record['ipv6'];
            }
        }

        return $addresses;
    }

    public function connect(string $host, int $port, int $timeoutSeconds): float
    {
        $startedAt = hrtime(true);
        $errorCode = 0;
        $errorMessage = '';

        $socket = $this->quietly(static function () use ($host, $port, $timeoutSeconds, &$errorCode, &$errorMessage): mixed {
            return stream_socket_client("tcp://{$host}:{$port}", $errorCode, $errorMessage, $timeoutSeconds);
        });

        if (! is_resource($socket)) {
            throw new ProbeFailed(is_string($errorMessage) && $errorMessage !== '' ? $errorMessage : "error {$errorCode}");
        }

        fclose($socket);

        return (hrtime(true) - $startedAt) / 1_000_000;
    }

    public function certificate(string $host, int $port, int $timeoutSeconds): CertificateInfo
    {
        $verified = $this->handshake($host, $port, $timeoutSeconds, true);

        if ($verified['certificate'] !== null) {
            return $this->describe($verified['certificate'], true, null);
        }

        $unverified = $this->handshake($host, $port, $timeoutSeconds, false);

        if ($unverified['certificate'] === null) {
            throw new ProbeFailed($unverified['error'] ?? $verified['error'] ?? 'TLS handshake failed');
        }

        return $this->describe($unverified['certificate'], false, $verified['error'] ?? 'the certificate is not trusted');
    }

    /**
     * @return array{certificate: mixed, error: ?string}
     */
    private function handshake(string $host, int $port, int $timeoutSeconds, bool $verify): array
    {
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => $verify,
            'verify_peer_name' => $verify,
            'allow_self_signed' => ! $verify,
            'peer_name' => $host,
            'SNI_enabled' => true,
        ]]);

        $errorCode = 0;
        $errorMessage = '';
        $socket = $this->quietly(static function () use ($host, $port, $timeoutSeconds, $context, &$errorCode, &$errorMessage): mixed {
            return stream_socket_client("ssl://{$host}:{$port}", $errorCode, $errorMessage, $timeoutSeconds, STREAM_CLIENT_CONNECT, $context);
        });

        if (! is_resource($socket)) {
            return ['certificate' => null, 'error' => is_string($errorMessage) && $errorMessage !== '' ? $errorMessage : 'TLS handshake failed'];
        }

        $params = stream_context_get_params($socket);
        fclose($socket);

        $ssl = is_array($params['options']['ssl'] ?? null) ? $params['options']['ssl'] : [];

        return ['certificate' => $ssl['peer_certificate'] ?? null, 'error' => null];
    }

    /**
     * Stream functions report failures as PHP warnings as well as return values;
     * the return value is all the probe needs.
     *
     * @param  callable(): mixed  $callback
     */
    private function quietly(callable $callback): mixed
    {
        set_error_handler(static fn (): bool => true);

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }

    private function describe(mixed $certificate, bool $trusted, ?string $error): CertificateInfo
    {
        $parsed = $certificate instanceof OpenSSLCertificate ? openssl_x509_parse($certificate) : false;

        if ($parsed === false) {
            return new CertificateInfo($trusted, error: $error);
        }

        $validTo = isset($parsed['validTo_time_t']) && is_numeric($parsed['validTo_time_t'])
            ? CarbonImmutable::createFromTimestampUTC((int) $parsed['validTo_time_t'])
            : null;

        $subject = is_array($parsed['subject'] ?? null) ? ($parsed['subject']['CN'] ?? null) : null;

        return new CertificateInfo($trusted, $validTo, is_string($subject) ? $subject : null, $error);
    }
}
