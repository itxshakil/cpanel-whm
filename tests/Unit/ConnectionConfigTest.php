<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Unit;

use Itxshakil\CpanelWhm\Exceptions\InvalidConfiguration;
use Itxshakil\CpanelWhm\Support\ConnectionConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConnectionConfigTest extends TestCase
{
    #[Test]
    public function a_bare_hostname_gets_https_and_port_2087(): void
    {
        $config = ConnectionConfig::fromArray('main', ['host' => 'Server.Example.com', 'token' => 'abc']);

        self::assertSame('https', $config->scheme);
        self::assertSame('server.example.com', $config->host);
        self::assertSame(2087, $config->port);
        self::assertSame('root', $config->user);
        self::assertSame('https://server.example.com:2087/json-api/listaccts', $config->endpoint('listaccts'));
    }

    #[Test]
    public function an_explicit_scheme_and_port_are_kept(): void
    {
        $config = ConnectionConfig::fromArray('main', ['host' => 'http://203.0.113.5', 'token' => 'abc', 'user' => 'reseller']);

        self::assertSame('http://203.0.113.5:2086', $config->baseUrl());
        self::assertFalse($config->usesTls());
        self::assertTrue($config->isIpAddress());
        self::assertSame('reseller', $config->user);
    }

    #[Test]
    #[DataProvider('cpanelPorts')]
    public function cpanel_and_webmail_ports_are_rejected(int $port): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('serves cPanel or Webmail');

        ConnectionConfig::fromArray('main', ['host' => "https://server.example.com:{$port}", 'token' => 'abc']);
    }

    /** @return array<string, array{int}> */
    public static function cpanelPorts(): array
    {
        return ['2082' => [2082], '2083' => [2083], '2095' => [2095], '2096' => [2096]];
    }

    /** @param array<string, mixed> $config */
    #[Test]
    #[DataProvider('incompleteConfigs')]
    public function a_missing_host_or_token_names_the_env_var_to_set(array $config, string $env): void
    {
        try {
            ConnectionConfig::fromArray('main', $config);
            self::fail('Expected InvalidConfiguration');
        } catch (InvalidConfiguration $invalidConfiguration) {
            self::assertStringContainsString($env, (string) $invalidConfiguration->hint());
        }
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function incompleteConfigs(): array
    {
        return [
            'no host' => [['token' => 'abc'], 'WHM_HOST'],
            'blank host' => [['host' => '  ', 'token' => 'abc'], 'WHM_HOST'],
            'no token' => [['host' => 'server.example.com'], 'WHM_TOKEN'],
        ];
    }

    #[Test]
    public function hosts_that_are_not_http_urls_are_rejected(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('invalid host');

        ConnectionConfig::fromArray('main', ['host' => 'ftp://server.example.com', 'token' => 'abc']);
    }

    #[Test]
    public function the_token_stays_out_of_dumps(): void
    {
        $config = ConnectionConfig::fromArray('main', ['host' => 'server.example.com', 'token' => 'SECRET-TOKEN']);

        $dump = print_r($config, true);

        self::assertStringNotContainsString('SECRET-TOKEN', $dump);
        self::assertStringContainsString('[REDACTED]', $dump);
    }

    #[Test]
    public function timeouts_are_at_least_one_second(): void
    {
        $config = ConnectionConfig::fromArray('main', ['host' => 'server.example.com', 'token' => 'abc', 'timeout' => 0, 'connect_timeout' => '5']);

        self::assertSame(1, $config->timeout);
        self::assertSame(5, $config->connectTimeout);
    }
}
