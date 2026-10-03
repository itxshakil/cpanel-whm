<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Unit;

use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Support\Redactor;
use Itxshakil\CpanelWhm\WhmRequest;
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
