<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use Illuminate\Support\Collection;
use Itxshakil\CpanelWhm\Data\AutoSslProblem;
use Itxshakil\CpanelWhm\Data\InstalledCertificate;
use Itxshakil\CpanelWhm\Data\Value;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Exceptions\WhmCommandFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Support\UsernameRules;
use Itxshakil\CpanelWhm\WhmResponse;
use SensitiveParameter;

/**
 * Certificates: start_autossl_check_for_one_user, get_autossl_problems_for_user,
 * installssl.
 *
 *     Whm::ssl()->runAutoSsl('acme');            // after adding a domain
 *     Whm::ssl()->autoSslProblems('acme');       // why a domain has no certificate
 */
class Ssl extends Module
{
    /**
     * Start an AutoSSL check for one account in the background. Returns the
     * process id WHM reports.
     *
     * @throws WhmException
     */
    public function runAutoSsl(string $user): ?int
    {
        return Value::int($this->client->call('start_autossl_check_for_one_user', ['username' => UsernameRules::normalise($user)], HttpMethod::Post)->get('pid'));
    }

    /**
     * @return Collection<int, AutoSslProblem>
     *
     * @throws WhmException
     */
    public function autoSslProblems(string $user): Collection
    {
        $response = $this->client->call('get_autossl_problems_for_user', ['username' => UsernameRules::normalise($user)]);

        return collect($this->rows($response->get('problems_by_domain')))->map(AutoSslProblem::fromArray(...))->values();
    }

    /**
     * Install a certificate on a domain's virtual host. The key is sent in the
     * POST body and redacted from logs and events.
     *
     * @throws WhmCommandFailed when installssl reports the install failed in data.status
     * @throws WhmException
     */
    public function install(string $domain, string $certificate, #[SensitiveParameter] string $key, ?string $caBundle = null, ?string $ip = null): InstalledCertificate
    {
        $response = $this->client->call('installssl', [
            'domain' => $domain,
            'crt' => $certificate,
            'key' => $key,
            'cab' => $caBundle,
            'ip' => $ip,
        ], HttpMethod::Post);

        // installssl reports its own outcome in data.status / data.statusmsg,
        // separately from metadata.result.
        if (array_key_exists('status', $response->data) && ! Value::bool($response->data['status'])) {
            $reason = Value::string($response->data['statusmsg'] ?? null) ?? 'installssl reported that the certificate was not installed.';

            throw WhmCommandFailed::from('installssl', new WhmResponse($response->data, [...$response->metadata, 'result' => 0, 'reason' => $reason], $response->status));
        }

        return InstalledCertificate::fromArray($response->data, $domain);
    }
}
