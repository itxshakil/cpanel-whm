<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Unit;

use Itxshakil\CpanelWhm\WhmResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WhmResponseTest extends TestCase
{
    #[Test]
    public function it_reads_success_command_and_data_with_dot_notation(): void
    {
        $response = WhmResponse::fromArray([
            'data' => ['acct' => [['user' => 'acme']]],
            'metadata' => ['result' => 1, 'reason' => 'OK', 'command' => 'listaccts'],
        ]);

        self::assertTrue($response->successful());
        self::assertSame('listaccts', $response->command());
        self::assertSame('acme', $response->get('acct.0.user'));
        self::assertSame('default', $response->get('missing', 'default'));
    }

    #[Test]
    public function it_falls_back_to_data_reason_when_metadata_has_none(): void
    {
        $response = WhmResponse::fromArray(['data' => ['reason' => 'User parameter is invalid'], 'metadata' => ['result' => 0]]);

        self::assertTrue($response->failed());
        self::assertSame('User parameter is invalid', $response->reason());
    }

    #[Test]
    public function a_failure_is_never_reported_as_ok(): void
    {
        self::assertSame('WHM reported a failure without a reason.', WhmResponse::fromArray(['metadata' => ['result' => 0]])->reason());
        self::assertSame('OK', WhmResponse::fromArray(['metadata' => ['result' => 1]])->reason());
    }

    #[Test]
    public function warnings_hidden_in_a_successful_call_are_exposed(): void
    {
        $response = WhmResponse::fromArray(['metadata' => [
            'result' => 1,
            'output' => ['warnings' => ['You cannot change backup settings.'], 'raw' => 'log line'],
        ]]);

        self::assertSame(['You cannot change backup settings.'], $response->warnings());
        self::assertSame('log line', $response->rawOutput());
        self::assertSame([], $response->messages());
    }

    #[Test]
    public function a_missing_or_odd_envelope_is_a_failure(): void
    {
        self::assertTrue(WhmResponse::fromArray([])->failed());
        self::assertSame([], WhmResponse::fromArray(['data' => 'x', 'metadata' => 'y'])->data);
    }
}
