<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Contracts;

use DateInterval;
use DateTimeInterface;
use Itxshakil\CpanelWhm\Api\WhmApi;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Modules\Accounts;
use Itxshakil\CpanelWhm\Modules\Backups;
use Itxshakil\CpanelWhm\Modules\CpanelUser;
use Itxshakil\CpanelWhm\Modules\Dns;
use Itxshakil\CpanelWhm\Modules\Domains;
use Itxshakil\CpanelWhm\Modules\Packages;
use Itxshakil\CpanelWhm\Modules\Quotas;
use Itxshakil\CpanelWhm\Modules\Resellers;
use Itxshakil\CpanelWhm\Modules\Server;
use Itxshakil\CpanelWhm\Modules\Sessions;
use Itxshakil\CpanelWhm\Modules\Ssl;
use Itxshakil\CpanelWhm\Modules\Suspensions;
use Itxshakil\CpanelWhm\Modules\Tokens;
use Itxshakil\CpanelWhm\Modules\Usage;
use Itxshakil\CpanelWhm\Support\ConnectionConfig;
use Itxshakil\CpanelWhm\WhmBatch;
use Itxshakil\CpanelWhm\WhmResponse;
use SensitiveParameter;

/**
 * One WHM server. Inject this interface; Whm::fake() swaps what is behind it.
 */
interface WhmClient
{
    /**
     * Call any WHM API 1 function. Throws on connection errors, HTTP errors and
     * when WHM reports failure (metadata.result = 0).
     *
     * Null values are left out, booleans are sent as 1/0 and lists as WHM's
     * repeated parameters (name, name-1, name-2, ...). Read-only functions are
     * retried after a connection error or HTTP 5xx.
     *
     * Without a $method the call uses the one cPanel documents for the function
     * (POST for undocumented ones). Parameters that hold a secret (password,
     * token, passphrase, ...) are always sent as POST.
     *
     * @param  array<string, mixed>  $params
     *
     * @throws WhmException
     */
    public function call(string $function, #[SensitiveParameter] array $params = [], ?HttpMethod $method = null, ?int $timeout = null): WhmResponse;

    /**
     * A copy of this client that caches read-only calls for $ttl:
     * Whm::cache(300)->accounts()->list(). Calls that change something are never cached.
     */
    public function cache(int|DateInterval|DateTimeInterface $ttl, ?string $store = null): static;

    /**
     * A copy of this client that always asks WHM, for reads that a change
     * depends on (a DNS zone's serial, for example).
     */
    public function withoutCache(): static;

    /**
     * A copy of this client that makes one attempt per call, for probes such
     * as health checks that time a single request.
     */
    public function withoutRetries(): static;

    /**
     * Dispatch one of the package's events (AccountCreated, ...) through Laravel.
     */
    public function dispatch(object $event): void;

    public function accounts(): Accounts;

    public function suspensions(): Suspensions;

    public function packages(): Packages;

    public function quotas(): Quotas;

    public function sessions(): Sessions;

    public function server(): Server;

    public function dns(): Dns;

    public function domains(): Domains;

    public function usage(): Usage;

    public function backups(): Backups;

    public function resellers(): Resellers;

    public function ssl(): Ssl;

    public function tokens(): Tokens;

    /**
     * Every documented WHM API 1 function as a typed method, grouped like cPanel's docs.
     */
    public function api(): WhmApi;

    /**
     * Several WHM API 1 functions in one request, through WHM's batch function:
     * Whm::batch()->add('version')->add('accountsummary', ['user' => 'acme'])->send().
     */
    public function batch(): WhmBatch;

    /**
     * Run cPanel UAPI functions as this account, through WHM's uapi_cpanel.
     */
    public function asUser(string $user): CpanelUser;

    public function config(): ConnectionConfig;
}
