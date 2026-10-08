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
     * Parameter names that hold a secret anywhere in the name: password, passwd,
     * passphrase, oldpass, mysql_pass, pass_hash, client_secret, private_key,
     * api_token, ... A bare "pass" must end the name and start a word, so flags
     * such as passive, showpass, spf_bypass or db_pass_update are left alone.
     */
    private const string SENSITIVE_PATTERN = '/(?:password|passwd|passphrase)(?:_hash)?$|(?:^|_|old|new)pass(?:_hash)?$|secret|private_?key|(?:^|_)token$|key_data|auth_?code|api_?key/';

    /**
     * @template TKey of array-key
     *
     * @param  array<TKey, mixed>  $data
     * @return array<TKey, mixed>
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
     * Masks cPanel security tokens (cpsess1234567890), login session ids
     * (session=user:hash) and secret parameters inside URLs and query strings.
     */
    public static function redactString(string $value): string
    {
        $value = (string) preg_replace('/cpsess\d+/', 'cpsess'.self::MASK, $value);
        $value = (string) preg_replace('/([?&]session=)[^&\s]+/', '$1'.self::MASK, $value);

        // Query strings inside a value, such as a batch command "passwd?user=acme&password=...".
        return (string) preg_replace_callback(
            '/([?&])([^=&?\s]+)=([^&\s]*)/',
            static fn (array $match): string => self::isSensitiveKey(rawurldecode($match[2])) && $match[3] !== self::MASK
                ? $match[1].$match[2].'='.self::MASK
                : $match[0],
            $value,
        );
    }

    /**
     * Whether a parameter name holds a secret. List parameters (password-1, ...)
     * count like their first entry.
     */
    public static function isSensitiveKey(string $key): bool
    {
        $lower = (string) preg_replace('/-\d+$/', '', mb_strtolower($key));

        if (in_array($lower, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        return preg_match(self::SENSITIVE_PATTERN, $lower) === 1;
    }

    /**
     * Whether any key, at any depth, holds a secret.
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function containsSensitive(array $data): bool
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                return true;
            }

            if (is_array($value) && self::containsSensitive($value)) {
                return true;
            }
        }

        return false;
    }
}
