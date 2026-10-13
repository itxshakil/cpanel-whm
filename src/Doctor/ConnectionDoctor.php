<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Doctor;

use Illuminate\Contracts\Config\Repository;
use Itxshakil\CpanelWhm\Contracts\WhmClient;
use Itxshakil\CpanelWhm\Enums\CheckStatus;
use Itxshakil\CpanelWhm\Exceptions\InvalidConfiguration;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Support\ConnectionConfig;
use Itxshakil\CpanelWhm\WhmManager;

/**
 * Checks a connection step by step and explains the first thing that is wrong:
 * config, DNS, the port, TLS, the token, its privileges, the functions the app
 * needs, the server version and latency.
 */
final readonly class ConnectionDoctor
{
    public const string CONFIG = 'Config';

    public const string DNS = 'DNS';

    public const string NETWORK = 'Network';

    public const string TLS = 'TLS';

    public const string AUTH = 'Auth';

    public const string PRIVILEGES = 'Privileges';

    public const string FUNCTIONS = 'Functions';

    public const string VERSION = 'Version';

    public const string LATENCY = 'Latency';

    /**
     * @var list<string>
     */
    public const array ORDER = [
        self::CONFIG, self::DNS, self::NETWORK, self::TLS, self::AUTH,
        self::PRIVILEGES, self::FUNCTIONS, self::VERSION, self::LATENCY,
    ];

    private const int CERTIFICATE_WARNING_DAYS = 14;

    public function __construct(
        private NetworkProbe $probe,
        private Repository $config,
    ) {}

    public function diagnose(WhmManager $manager, string $connection): ConnectionReport
    {
        $results = [];
        $target = $connection;

        try {
            $config = $manager->configFor($connection);
            $target = $config->baseUrl();
            $results[] = CheckResult::pass(self::CONFIG, "{$config->user} @ {$config->baseUrl()}, TLS verification ".($config->verifyTls ? 'on' : 'off'));
        } catch (InvalidConfiguration $invalidConfiguration) {
            $results[] = CheckResult::fail(self::CONFIG, $invalidConfiguration->getMessage(), $invalidConfiguration->hint());

            return $this->finish($connection, $target, $results);
        }

        if ($manager->isFaked()) {
            foreach ([self::DNS, self::NETWORK, self::TLS] as $name) {
                $results[] = CheckResult::skip($name, 'skipped: Whm::fake() is active');
            }
        } else {
            foreach ([$this->dns(...), $this->network(...), $this->tls(...)] as $check) {
                $results[] = $result = $check($config);

                if ($result->status === CheckStatus::Fail) {
                    return $this->finish($connection, $target, $results);
                }
            }
        }

        $client = $manager->connection($connection);
        $startedAt = hrtime(true);

        try {
            $version = $client->server()->version();
        } catch (WhmException $whmException) {
            $results[] = CheckResult::fail(
                self::AUTH,
                $whmException->getMessage(),
                $whmException->hint(),
            );

            return $this->finish($connection, $target, $results);
        }

        $latencyMs = (hrtime(true) - $startedAt) / 1_000_000;
        $results[] = CheckResult::pass(self::AUTH, "token accepted for {$config->user}");

        foreach ([$this->privileges($client), $this->functions($client)] as $result) {
            $results[] = $result;

            if ($result->status === CheckStatus::Fail) {
                return $this->finish($connection, $target, $results);
            }
        }

        $results[] = $this->version($version);
        $results[] = $this->latency($latencyMs, $config);

        return $this->finish($connection, $target, $results);
    }

    private function dns(ConnectionConfig $config): CheckResult
    {
        if ($config->isIpAddress()) {
            return CheckResult::pass(self::DNS, "{$config->host} is an IP address, no lookup needed");
        }

        $addresses = $this->probe->resolve($config->host);

        if ($addresses === []) {
            return CheckResult::fail(self::DNS, "{$config->host} does not resolve", 'Check the spelling of WHM_HOST and that the name has an A record.');
        }

        return CheckResult::pass(self::DNS, "{$config->host} → ".implode(', ', array_slice($addresses, 0, 3)));
    }

    private function network(ConnectionConfig $config): CheckResult
    {
        try {
            $milliseconds = $this->probe->connect($config->host, $config->port, $config->connectTimeout);
        } catch (ProbeFailed $probeFailed) {
            return CheckResult::fail(
                self::NETWORK,
                "port {$config->port} unreachable: {$probeFailed->getMessage()}",
                "Open port {$config->port} to this server's IP in the WHM server's firewall (CSF, firewalld), and check cPHulk has not blocked it.",
            );
        }

        return CheckResult::pass(self::NETWORK, sprintf('port %d open (%d ms)', $config->port, (int) round($milliseconds)));
    }

    private function tls(ConnectionConfig $config): CheckResult
    {
        if (! $config->usesTls()) {
            return CheckResult::warn(self::TLS, "plain HTTP on port {$config->port}: the token is sent unencrypted", 'Use https:// and port 2087.');
        }

        try {
            $certificate = $this->probe->certificate($config->host, $config->port, $config->connectTimeout, $config->verifyTls ? $config->caBundle : null);
        } catch (ProbeFailed $probeFailed) {
            return CheckResult::fail(self::TLS, "TLS handshake failed: {$probeFailed->getMessage()}", "Check that port {$config->port} serves WHM over HTTPS.");
        }

        $expiry = $certificate->validTo?->format('Y-m-d');
        $pinHint = $certificate->publicKeyPin !== null ? " Its public key pin is {$certificate->publicKeyPin}." : '';

        if ($config->pinnedPublicKey !== null && $certificate->publicKeyPin !== null) {
            if (! in_array($certificate->publicKeyPin, explode(';', $config->pinnedPublicKey), true)) {
                return CheckResult::fail(
                    self::TLS,
                    "the certificate's public key doesn't match WHM_PINNED_PUBLIC_KEY",
                    "The server's key changed, or something is intercepting the connection.{$pinHint} Update the pin only once you know why.",
                );
            }

            if (! $certificate->trusted) {
                return CheckResult::pass(self::TLS, 'certificate not trusted by a CA, but its public key matches the pin');
            }
        }

        if (! $certificate->trusted) {
            $why = $certificate->error ?? 'the certificate is not trusted';

            return $config->verifyTls
                ? CheckResult::fail(
                    self::TLS,
                    "certificate not trusted: {$why}",
                    "Install a valid certificate for {$config->host} (AutoSSL covers the server hostname), set WHM_CA_BUNDLE to the CA that signed it, or set WHM_VERIFY_TLS=false and pin its key with WHM_PINNED_PUBLIC_KEY if you connect by IP.{$pinHint}",
                )
                : CheckResult::warn(self::TLS, 'certificate not trusted; verification is off (WHM_VERIFY_TLS=false)', "Pin the certificate's key with WHM_PINNED_PUBLIC_KEY so the token cannot be intercepted.{$pinHint}");
        }

        if ($certificate->validTo !== null && $certificate->validTo->lessThan(now()->addDays(self::CERTIFICATE_WARNING_DAYS))) {
            return CheckResult::warn(self::TLS, "certificate expires on {$expiry}", 'Renew it soon, or calls will start failing.');
        }

        return CheckResult::pass(self::TLS, $expiry !== null ? "certificate trusted, valid until {$expiry}" : 'certificate trusted');
    }

    private function privileges(WhmClient $client): CheckResult
    {
        $required = $this->stringList('cpanel-whm.doctor.required_privileges');

        try {
            $privileges = $client->server()->privileges();
        } catch (WhmException $whmException) {
            return $required === []
                ? CheckResult::warn(self::PRIVILEGES, "could not read privileges: {$whmException->getMessage()}")
                : CheckResult::fail(self::PRIVILEGES, "could not read privileges: {$whmException->getMessage()}", $whmException->hint());
        }

        if (in_array('all', $privileges, true)) {
            return CheckResult::pass(self::PRIVILEGES, 'all privileges (root or full-access token)');
        }

        $missing = array_values(array_diff($required, $privileges));

        if ($missing !== []) {
            return CheckResult::fail(
                self::PRIVILEGES,
                'missing: '.implode(', ', $missing),
                "Grant them in WHM > Manage API Tokens (the token's ACL), or in the reseller's ACL.",
            );
        }

        return CheckResult::pass(self::PRIVILEGES, count($privileges).' privileges'.($required === [] ? '' : ', all required ones present'));
    }

    private function functions(WhmClient $client): CheckResult
    {
        $required = $this->stringList('cpanel-whm.doctor.required_functions');

        try {
            $functions = $client->server()->functions();
        } catch (WhmException $whmException) {
            return $required === []
                ? CheckResult::warn(self::FUNCTIONS, "could not list functions: {$whmException->getMessage()}")
                : CheckResult::fail(self::FUNCTIONS, "could not list functions: {$whmException->getMessage()}", $whmException->hint());
        }

        $missing = array_values(array_diff($required, $functions));

        if ($missing !== []) {
            return CheckResult::fail(
                self::FUNCTIONS,
                'not available on this server: '.implode(', ', $missing),
                'Update cPanel & WHM, or check the function names in config/cpanel-whm.php.',
            );
        }

        return CheckResult::pass(self::FUNCTIONS, count($functions).' functions available'.($required === [] ? '' : ', all required ones present'));
    }

    private function version(string $version): CheckResult
    {
        $minimum = $this->config->get('cpanel-whm.doctor.minimum_version');
        $minimum = is_string($minimum) ? $minimum : '0';

        if ($version === '') {
            return CheckResult::warn(self::VERSION, 'the server did not report its version');
        }

        if (version_compare($version, $minimum, '<')) {
            return CheckResult::warn(self::VERSION, "cPanel & WHM {$version} is older than {$minimum}", 'Some functions may be missing or behave differently. Update the server if you can.');
        }

        return CheckResult::pass(self::VERSION, "cPanel & WHM {$version}");
    }

    private function latency(float $milliseconds, ConnectionConfig $config): CheckResult
    {
        $rounded = (int) round($milliseconds);

        if ($milliseconds > $config->timeout * 1000 * 0.5) {
            return CheckResult::warn(self::LATENCY, "{$rounded} ms, over half the {$config->timeout} s timeout", 'Raise WHM_TIMEOUT or check the network path to the server.');
        }

        return CheckResult::pass(self::LATENCY, "{$rounded} ms");
    }

    /**
     * @param  list<CheckResult>  $results
     */
    private function finish(string $connection, string $target, array $results): ConnectionReport
    {
        $done = array_map(static fn (CheckResult $result): string => $result->name, $results);

        foreach (self::ORDER as $name) {
            if (! in_array($name, $done, true)) {
                $results[] = CheckResult::skip($name);
            }
        }

        return new ConnectionReport($connection, $target, $results);
    }

    /**
     * @return list<string>
     */
    private function stringList(string $key): array
    {
        $value = $this->config->get($key);

        return is_array($value) ? array_values(array_map(strval(...), array_filter($value, is_scalar(...)))) : [];
    }
}
