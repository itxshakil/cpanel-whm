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
    public function a_name_with_only_an_aaaa_record_resolves(): void
    {
        $probe = new SocketProbe(
            lookupIpv4: static fn (string $host): array => [],
            lookupIpv6: static fn (string $host): array => $host === 'v6only.example.com' ? ['2001:db8::10'] : [],
        );

        self::assertSame(['2001:db8::10'], $probe->resolve('v6only.example.com'));
        self::assertSame([], $probe->resolve('nowhere.example.com'));
    }

    #[Test]
    public function both_address_families_are_returned_once(): void
    {
        $probe = new SocketProbe(
            lookupIpv4: static fn (string $host): array => ['203.0.113.10', '203.0.113.10'],
            lookupIpv6: static fn (string $host): array => ['2001:db8::10'],
        );

        self::assertSame(['203.0.113.10', '2001:db8::10'], $probe->resolve('server.example.com'));
    }

    #[Test]
    public function the_container_builds_the_real_lookups(): void
    {
        $probe = (new \Illuminate\Container\Container)->make(SocketProbe::class);

        self::assertContains('127.0.0.1', $probe->resolve('localhost'));
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
