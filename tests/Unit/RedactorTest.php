<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Unit;

use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Support\Redactor;
use Itxshakil\CpanelWhm\WhmRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RedactorTest extends TestCase
{
    #[Test]
    public function secret_keys_are_masked_at_any_depth(): void
    {
        $redacted = Redactor::redact([
            'user' => 'acme',
            'password' => 'hunter2',
            'nested' => ['api_token' => 'abc', 'contactPassword' => 'x', 'csrf_token' => 'y'],
        ]);

        self::assertSame([
            'user' => 'acme',
            'password' => '[REDACTED]',
            'nested' => ['api_token' => '[REDACTED]', 'contactPassword' => '[REDACTED]', 'csrf_token' => '[REDACTED]'],
        ], $redacted);
    }

    #[Test]
    #[DataProvider('secretKeys')]
    public function secret_parameter_names_from_the_cpanel_specs_are_masked(string $key): void
    {
        self::assertTrue(Redactor::isSensitiveKey($key), "{$key} should be treated as secret");
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function secretKeys(): iterable
    {
        foreach ([
            'client_secret', 'passphrase', 'key_passphrase', 'sshkey_passphrase', 'oldpass', 'mysql_pass',
            'privatekey', 'password_hash', 'pass_hash', 'key_data', 'password-1', 'newpass-2', 'dbpassword',
        ] as $key) {
            yield $key => [$key];
        }
    }

    #[Test]
    #[DataProvider('ordinaryKeys')]
    public function ordinary_parameter_names_are_left_alone(string $key): void
    {
        self::assertFalse(Redactor::isSensitiveKey($key), "{$key} should not be treated as secret");
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function ordinaryKeys(): iterable
    {
        foreach (['user', 'domain', 'token_name', 'passive', 'db_pass_update', 'showpass', 'spf_bypass', 'plan', 'contactemail', 'keyword'] as $key) {
            yield $key => [$key];
        }
    }

    #[Test]
    public function session_tokens_inside_urls_are_masked(): void
    {
        $url = 'https://server.example.com:2083/cpsess1234567890/login/?session=acme:abc';

        self::assertSame(
            'https://server.example.com:2083/cpsess[REDACTED]/login/?session=[REDACTED]',
            Redactor::redact(['url' => $url])['url'],
        );
    }

    #[Test]
    public function a_dumped_request_does_not_show_secrets(): void
    {
        $request = new WhmRequest('main', 'passwd', ['user' => 'acme', 'password' => 'hunter2'], HttpMethod::Post);

        $dump = print_r($request, true);

        self::assertStringNotContainsString('hunter2', $dump);
        self::assertStringContainsString('[REDACTED]', $dump);
    }
}
