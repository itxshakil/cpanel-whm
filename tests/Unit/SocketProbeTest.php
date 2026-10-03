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
