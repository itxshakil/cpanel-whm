<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm;

use Illuminate\Contracts\Events\Dispatcher;
use Itxshakil\CpanelWhm\Contracts\Transport;
use Itxshakil\CpanelWhm\Contracts\WhmClient as WhmClientContract;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Events\WhmRequestFailed;
use Itxshakil\CpanelWhm\Events\WhmRequestSending;
use Itxshakil\CpanelWhm\Events\WhmResponseReceived;
use Itxshakil\CpanelWhm\Exceptions\WhmAuthenticationFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmCommandFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Exceptions\WhmHttpError;
use Itxshakil\CpanelWhm\Exceptions\WhmPermissionDenied;
use Itxshakil\CpanelWhm\Modules\Accounts;
use Itxshakil\CpanelWhm\Modules\CpanelUser;
use Itxshakil\CpanelWhm\Modules\Packages;
use Itxshakil\CpanelWhm\Modules\Quotas;
use Itxshakil\CpanelWhm\Modules\Server;
use Itxshakil\CpanelWhm\Modules\Sessions;
use Itxshakil\CpanelWhm\Modules\Suspensions;
use Itxshakil\CpanelWhm\Support\ConnectionConfig;
use Itxshakil\CpanelWhm\Transport\TransportResponse;

final class WhmClient implements WhmClientContract
{
    /**
     * Phrases WHM uses when a token's ACL does not cover a function.
     *
     * @var list<string>
     */
    private const array PERMISSION_PHRASES = [
        'access denied',
        'permission denied',
        'you do not have permission',
        'you do not have access',
        'not have the privilege',
        'requires the privilege',
    ];

    private ?Accounts $accounts = null;

    private ?Suspensions $suspensions = null;

    private ?Packages $packages = null;

    private ?Quotas $quotas = null;

    private ?Sessions $sessions = null;

    private ?Server $server = null;

    public function __construct(
        private readonly ConnectionConfig $config,
        private readonly Transport $transport,
        private readonly ?Dispatcher $events = null,
    ) {}

    public function call(string $function, array $params = [], HttpMethod $method = HttpMethod::Get, ?int $timeout = null): WhmResponse
    {
        $request = new WhmRequest($this->config->name, $function, $params, $method, $timeout);

        $this->events?->dispatch(new WhmRequestSending($request));

        try {
            $raw = $this->transport->send($this->config, $request);
            $response = $this->interpret($request, $raw);
        } catch (WhmException $whmException) {
            $this->events?->dispatch(new WhmRequestFailed($request, $whmException));

            throw $whmException;
        }

        $this->events?->dispatch(new WhmResponseReceived($request, $response, $raw->durationMs));

        return $response;
    }

    public function accounts(): Accounts
    {
        return $this->accounts ??= new Accounts($this);
    }

    public function suspensions(): Suspensions
    {
        return $this->suspensions ??= new Suspensions($this);
    }

    public function packages(): Packages
    {
        return $this->packages ??= new Packages($this);
    }

    public function quotas(): Quotas
    {
        return $this->quotas ??= new Quotas($this);
    }

    public function sessions(): Sessions
    {
        return $this->sessions ??= new Sessions($this);
    }

    public function server(): Server
    {
        return $this->server ??= new Server($this);
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
            throw $this->isPermissionFailure($response->reason())
                ? WhmPermissionDenied::from($request->function, $response)
                : WhmCommandFailed::from($request->function, $response);
        }

        return $response;
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

    private function isPermissionFailure(string $reason): bool
    {
        $reason = mb_strtolower($reason);

        foreach (self::PERMISSION_PHRASES as $phrase) {
            if (str_contains($reason, $phrase)) {
                return true;
            }
        }

        return false;
    }
}
