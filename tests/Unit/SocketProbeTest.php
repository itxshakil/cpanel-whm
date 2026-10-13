<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Unit;

use Itxshakil\CpanelWhm\Doctor\ProbeFailed;
use Itxshakil\CpanelWhm\Doctor\SocketProbe;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SocketProbeTest extends TestCase
{
    #[Test]
    public function an_ip_address_needs_no_lookup(): void
    {
        self::assertSame(['203.0.113.10'], (new SocketProbe)->resolve('203.0.113.10'));
    }

    #[Test]
    public function certificate_problems_are_told_apart_from_network_errors(): void
    {
        // Messages as PHP and OpenSSL word them (seen against badssl.com).
        self::assertTrue(SocketProbe::isCertificateProblem('SSL operation failed with code 1. OpenSSL Error messages: error:0A000086:SSL routines::certificate verify failed'));
        self::assertTrue(SocketProbe::isCertificateProblem("Peer certificate CN=`*.badssl.com' did not match expected CN=`wrong.host.badssl.com'"));

        self::assertFalse(SocketProbe::isCertificateProblem('Unable to connect to ssl://10.255.255.1:443 (Connection timed out)'));
        self::assertFalse(SocketProbe::isCertificateProblem('Unable to connect to ssl://127.0.0.1:1 (Connection refused)'));
        self::assertFalse(SocketProbe::isCertificateProblem('TLS handshake failed'));
    }

    #[Test]
    public function a_network_error_during_the_handshake_is_not_blamed_on_the_certificate(): void
    {
        try {
            (new SocketProbe)->certificate('127.0.0.1', 1, 1);
            self::fail('Expected ProbeFailed');
        } catch (ProbeFailed $probeFailed) {
            self::assertFalse(SocketProbe::isCertificateProblem($probeFailed->getMessage()));
            self::assertStringContainsString('127.0.0.1:1', $probeFailed->getMessage());
        }
    }

    #[Test]
    public function a_refused_connection_is_a_probe_failure(): void
    {
        $this->expectException(ProbeFailed::class);

        (new SocketProbe)->connect('127.0.0.1', 1, 1);
    }

    #[Test]
    public function a_handshake_that_cannot_start_is_a_probe_failure(): void
    {
        $this->expectException(ProbeFailed::class);

        (new SocketProbe)->certificate('127.0.0.1', 1, 1);
    }
}
