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

    #[Test]
    public function the_public_key_pin_matches_what_openssl_and_curl_compute(): void
    {
        // A throwaway self-signed certificate. The expected pin came from:
        // openssl x509 -pubkey -noout | openssl pkey -pubin -outform der | openssl dgst -sha256 -binary | base64
        $pem = <<<'PEM'
            -----BEGIN CERTIFICATE-----
            MIIBfTCCASOgAwIBAgIUBh+6nPUvh+r3PweREXKMtqXs4SYwCgYIKoZIzj0EAwIw
            EzERMA8GA1UEAwwId2htLnRlc3QwIBcNMjYxMDEzMDg0MDM5WhgPMjEyNjA5MTkw
            ODQwMzlaMBMxETAPBgNVBAMMCHdobS50ZXN0MFkwEwYHKoZIzj0CAQYIKoZIzj0D
            AQcDQgAEQx/09Cv3bz+2mjn72m/LYfTgIsw206TZWc+VhorYHGwmzcFqslSWjw1m
            qVKnNfQZHdPtNuzYtmPCxvSH4FsRs6NTMFEwHQYDVR0OBBYEFGK++bTBMuEVBRJB
            s08WI8BttW9wMB8GA1UdIwQYMBaAFGK++bTBMuEVBRJBs08WI8BttW9wMA8GA1Ud
            EwEB/wQFMAMBAf8wCgYIKoZIzj0EAwIDSAAwRQIhAKNR2/13mTT+eOqzZR63FWmC
            /UWmK7lviLE6tl3JGwsNAiAMKGQdAJgWrFeclW+c/gWsWj3kJkScQBoc6WY+A5du
            4Q==
            -----END CERTIFICATE-----
            PEM;

        $certificate = openssl_x509_read($pem);
        self::assertNotFalse($certificate);

        self::assertSame('sha256//gGNHnDZ2ExU9zt20GOHgujMjLv5qBH1tEyia3gZ/93s=', SocketProbe::publicKeyPin($certificate));
    }
}
