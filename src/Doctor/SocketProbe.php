<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Doctor;

use Carbon\CarbonImmutable;
use OpenSSLCertificate;

/**
 * NetworkProbe on PHP streams: DNS lookup, TCP connect and a TLS handshake
 * that captures the server certificate.
 */
final class SocketProbe implements NetworkProbe
{
    public function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $addresses = gethostbynamel($host);

        return $addresses === false ? [] : $addresses;
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

        // Only a rejected certificate is worth a second, unverified look. A
        // timeout or reset is a network problem; retrying and succeeding would
        // report it as an untrusted certificate.
        if (! self::isCertificateProblem($verified['error'] ?? '')) {
            throw new ProbeFailed($verified['error'] ?? 'TLS handshake failed');
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
        $warnings = [];
        $socket = $this->quietly(static function () use ($host, $port, $timeoutSeconds, $context, &$errorCode, &$errorMessage): mixed {
            return stream_socket_client("ssl://{$host}:{$port}", $errorCode, $errorMessage, $timeoutSeconds, STREAM_CLIENT_CONNECT, $context);
        }, $warnings);

        if (! is_resource($socket)) {
            // OpenSSL's reason ("certificate verify failed") only reaches the
            // warnings, among generic ones such as "Failed to enable crypto".
            $reasons = array_values(array_filter(
                array_map(
                    static fn (string $reason): string => trim((string) preg_replace('/\s+/', ' ', $reason)),
                    [...$warnings, is_string($errorMessage) ? $errorMessage : ''],
                ),
                static fn (string $reason): bool => $reason !== '',
            ));
            $certificateReasons = array_values(array_filter($reasons, self::isCertificateProblem(...)));

            return ['certificate' => null, 'error' => $certificateReasons[0] ?? $reasons[0] ?? 'TLS handshake failed'];
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
     * @param  list<string>  $warnings  the warning messages, without the function prefix
     */
    private function quietly(callable $callback, array &$warnings = []): mixed
    {
        set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
            $warnings[] = (string) preg_replace('/^stream_socket_client\(\): /', '', $message);

            return true;
        });

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Whether a handshake failed because of the certificate (untrusted, self
     * signed, expired, wrong name) rather than the network.
     */
    public static function isCertificateProblem(string $error): bool
    {
        return preg_match('/certificate|verify failed|self[- ]signed|issuer|did not match expected CN|peer_name/i', $error) === 1;
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
