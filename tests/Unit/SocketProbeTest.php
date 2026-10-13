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
    public function ipv6_addresses_get_brackets_in_stream_urls(): void
    {
        self::assertSame('[2001:db8::1]:2087', SocketProbe::address('2001:db8::1', 2087));
        self::assertSame('203.0.113.10:2087', SocketProbe::address('203.0.113.10', 2087));
        self::assertSame('server.example.com:2087', SocketProbe::address('server.example.com', 2087));
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
