<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Support;

/**
 * Removes secrets from request parameters and response data before they are
 * logged, printed or put into an event.
 */
final class Redactor
{
    public const string MASK = '[REDACTED]';

    /**
     * Keys whose values are always secret, compared case-insensitively.
     *
     * @var list<string>
     */
    public const array SENSITIVE_KEYS = [
        'password',
        'pass',
        'passwd',
        'newpass',
        'token',
        'api_token',
        'apitoken',
        'api_key',
        'apikey',
        'secret',
        'authorization',
        'cp_security_token',
        'session',
        'authcode',
        'auth_code',
        'private_key',
        'key',
    ];

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $data[$key] = self::MASK;

                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::redact($value);

                continue;
            }

            if (is_string($value)) {
                $data[$key] = self::redactString($value);
            }
        }

        return $data;
    }

    /**
     * Masks cPanel security tokens (cpsess1234567890) and login session ids
     * (session=user:hash) inside URLs.
     */
    public static function redactString(string $value): string
    {
        $value = (string) preg_replace('/cpsess\d+/', 'cpsess'.self::MASK, $value);

        return (string) preg_replace('/([?&]session=)[^&\s]+/', '$1'.self::MASK, $value);
    }

    public static function isSensitiveKey(string $key): bool
    {
        $lower = mb_strtolower($key);

        if (in_array($lower, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        return str_ends_with($lower, 'password') || str_ends_with($lower, '_token');
    }
}
