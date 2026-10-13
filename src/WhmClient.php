<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm;

use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Itxshakil\CpanelWhm\Api\WhmApi;
use Itxshakil\CpanelWhm\Contracts\Transport;
use Itxshakil\CpanelWhm\Contracts\WhmClient as WhmClientContract;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Events\WhmRequestFailed;
use Itxshakil\CpanelWhm\Events\WhmRequestSending;
use Itxshakil\CpanelWhm\Events\WhmResponseReceived;
use Itxshakil\CpanelWhm\Exceptions\InvalidConfiguration;
use Itxshakil\CpanelWhm\Exceptions\UapiCallFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmAuthenticationFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmCommandFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmConnectionFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Exceptions\WhmHttpError;
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
use Itxshakil\CpanelWhm\Modules\Transfers;
use Itxshakil\CpanelWhm\Modules\Usage;
use Itxshakil\CpanelWhm\Support\ConnectionConfig;
use Itxshakil\CpanelWhm\Support\FunctionCatalog;
use Itxshakil\CpanelWhm\Support\Params;
use Itxshakil\CpanelWhm\Transport\TransportResponse;
use SensitiveParameter;

final class WhmClient implements WhmClientContract
{
    /**
     * Read-only functions whose answer must always be current: whether a
     * username is free is checked right before an account is created.
     *
     * @var list<string>
     */
    private const array NEVER_CACHED = ['verify_new_username'];

    /**
     * @var array<class-string, object>
     */
    private array $modules = [];

    private ?WhmApi $api = null;

    /**
     * @param  int|DateInterval|DateTimeInterface|null  $cacheTtl  set by cache(); null means no caching
     */
    public function __construct(
        private readonly ConnectionConfig $config,
        private readonly Transport $transport,
        private readonly ?Dispatcher $events = null,
        private readonly ?CacheFactory $cache = null,
        private readonly int|DateInterval|DateTimeInterface|null $cacheTtl = null,
        private readonly ?string $cacheStore = null,
        private readonly string $cachePrefix = 'cpanel-whm',
    ) {}

    public function call(string $function, #[SensitiveParameter] array $params = [], ?HttpMethod $method = null, ?int $timeout = null): WhmResponse
    {
        $params = Params::normalise($params);
        $method = HttpMethod::for($function, $params, $method);
        $readOnly = FunctionCatalog::isReadOnly($function);

        if ($readOnly && $this->cacheTtl !== null && $this->cache instanceof CacheFactory && ! in_array($function, self::NEVER_CACHED, true)) {
            $store = $this->cache->store($this->cacheStore);
            $key = $this->cacheKeyPrefix($store).':'.$function.':'.hash('xxh128', serialize($params));
            $cached = $store->get($key);

            if ($cached instanceof WhmResponse) {
                return $cached;
            }

            $response = $this->send($function, $params, $method, $timeout, $readOnly);
            $store->put($key, $response, $this->cacheTtl);

            return $response;
        }

        $response = $this->send($function, $params, $method, $timeout, $readOnly);

        // A change makes this server's cached reads stale (a new account, a suspension, ...).
        if (! $readOnly) {
            $this->flushCache();
        }

        return $response;
    }

    public function flushCache(): void
    {
        if (! $this->cache instanceof CacheFactory) {
            return;
        }

        // Bumping a generation number drops every cached read for this server at
        // once, on any cache store, without knowing the keys.
        $this->cache->store($this->cacheStore)->increment($this->generationKey());
    }

    public function cache(int|DateInterval|DateTimeInterface $ttl, ?string $store = null): static
    {
        if (! $this->cache instanceof CacheFactory) {
            throw new InvalidConfiguration('Whm::cache() needs Laravel\'s cache; this client was built without it.');
        }

        return new self($this->config, $this->transport, $this->events, $this->cache, $ttl, $store ?? $this->cacheStore, $this->cachePrefix);
    }

    public function withoutCache(): static
    {
        return $this->cacheTtl === null
            ? $this
            : new self($this->config, $this->transport, $this->events, $this->cache, null, $this->cacheStore, $this->cachePrefix);
    }

    public function withoutRetries(): static
    {
        return new self($this->config->withoutRetries(), $this->transport, $this->events, $this->cache, $this->cacheTtl, $this->cacheStore, $this->cachePrefix);
    }

    public function timeout(int $seconds, ?int $connect = null): static
    {
        return new self($this->config->withTimeouts($seconds, $connect), $this->transport, $this->events, $this->cache, $this->cacheTtl, $this->cacheStore, $this->cachePrefix);
    }

    public function dispatch(object $event): void
    {
        $this->events?->dispatch($event);
    }

    public function accounts(): Accounts
    {
        return $this->module(Accounts::class);
    }

    public function suspensions(): Suspensions
    {
        return $this->module(Suspensions::class);
    }

    public function packages(): Packages
    {
        return $this->module(Packages::class);
    }

    public function quotas(): Quotas
    {
        return $this->module(Quotas::class);
    }

    public function sessions(): Sessions
    {
        return $this->module(Sessions::class);
    }

    public function server(): Server
    {
        return $this->module(Server::class);
    }

    public function dns(): Dns
    {
        return $this->module(Dns::class);
    }

    public function domains(): Domains
    {
        return $this->module(Domains::class);
    }

    public function usage(): Usage
    {
        return $this->module(Usage::class);
    }

    public function backups(): Backups
    {
        return $this->module(Backups::class);
    }

    public function resellers(): Resellers
    {
        return $this->module(Resellers::class);
    }

    public function ssl(): Ssl
    {
        return $this->module(Ssl::class);
    }

    public function tokens(): Tokens
    {
        return $this->module(Tokens::class);
    }

    public function transfers(): Transfers
    {
        return $this->module(Transfers::class);
    }

    public function api(): WhmApi
    {
        return $this->api ??= new WhmApi($this);
    }

    public function batch(): WhmBatch
    {
        return new WhmBatch($this);
    }

    public function asUser(string $user): CpanelUser
    {
        return new CpanelUser($this, $user);
    }

    public function config(): ConnectionConfig
    {
        return $this->config;
    }

    /**
     * The server and user are part of the key, so two apps or tenants that
     * reuse a connection name never read each other's answers. The generation
     * changes on every flushCache().
     */
    private function cacheKeyPrefix(Repository $store): string
    {
        $generation = $store->get($this->generationKey());

        return $this->cachePrefix.':'.$this->config->name.':'.$this->serverHash().':g'.(is_int($generation) ? $generation : 0);
    }

    private function generationKey(): string
    {
        return $this->cachePrefix.':'.$this->config->name.':'.$this->serverHash().':generation';
    }

    private function serverHash(): string
    {
        return hash('xxh128', $this->config->baseUrl().'|'.$this->config->user);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T
     */
    private function module(string $class): object
    {
        $module = $this->modules[$class] ??= new $class($this);

        assert($module instanceof $class);

        return $module;
    }

    /**
     * Send once, or, for read-only functions, retry after a connection error
     * or an HTTP 5xx. A function that changes something is never repeated.
     *
     * @param  array<string, mixed>  $params
     *
     * @throws WhmException
     */
    private function send(string $function, array $params, HttpMethod $method, ?int $timeout, bool $readOnly): WhmResponse
    {
        $attempts = $readOnly ? 1 + $this->config->retries : 1;

        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->attempt(new WhmRequest($this->config->name, $function, $params, $method, $timeout));
            } catch (WhmConnectionFailed|WhmHttpError $exception) {
                $retryable = $exception instanceof WhmConnectionFailed || $exception->status() >= 500;

                if (! $retryable || $attempt >= $attempts) {
                    throw $exception;
                }

                if ($this->config->retryDelayMs > 0) {
                    usleep($this->config->retryDelayMs * 1000 * $attempt);
                }
            }
        }
    }

    /**
     * @throws WhmException
     */
    private function attempt(WhmRequest $request): WhmResponse
    {
        // Listeners get redacted copies: events end up in Telescope, queued
        // listeners and logs, none of which may see a password or a new token.
        $this->events?->dispatch(new WhmRequestSending($request->redacted()));

        try {
            $raw = $this->transport->send($this->config, $request);
            $response = $this->interpret($request, $raw);
        } catch (WhmException $whmException) {
            $this->events?->dispatch(new WhmRequestFailed($request->redacted(), $whmException));

            throw $whmException;
        }

        $this->events?->dispatch(new WhmResponseReceived($request->redacted(), $response->redacted(), $raw->durationMs));

        return $response;
    }

    /**
     * @throws WhmException
     */
    private function interpret(WhmRequest $request, TransportResponse $raw): WhmResponse
    {
        $json = $raw->json;
        $reason = is_array($json) ? $this->reasonFrom($json) : null;

        if ($raw->status === 401 || $raw->status === 403) {
            throw WhmAuthenticationFailed::forStatus($raw->status, $request->function, $reason);
        }

        if ($raw->status >= 400) {
            throw WhmHttpError::forStatus($raw->status, $request->function, $reason);
        }

        if ($json === null) {
            throw WhmHttpError::notJson($raw->status, $request->function);
        }

        $response = WhmResponse::fromArray($json, $raw->status);

        if ($response->failed()) {
            throw WhmCommandFailed::classify($request->function, $response);
        }

        $this->guardInnerCall($request, $response);

        return $response;
    }

    /**
     * The inner cPanel function of uapi_cpanel and cpanel is checked for every
     * caller, including raw Whm::call().
     *
     * @throws UapiCallFailed
     */
    private function guardInnerCall(WhmRequest $request, WhmResponse $response): void
    {
        $failure = UapiCallFailed::fromInnerCall($request->function, $request->params, $response);

        if ($failure instanceof UapiCallFailed) {
            throw $failure;
        }
    }

    /**
     * @param  array<array-key, mixed>  $json
     */
    private function reasonFrom(array $json): ?string
    {
        $metadata = $json['metadata'] ?? null;
        $reason = is_array($metadata) ? ($metadata['reason'] ?? null) : null;

        return is_string($reason) ? $reason : null;
    }
}
