<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Transport;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Itxshakil\CpanelWhm\Contracts\Transport;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Exceptions\WhmConnectionFailed;
use Itxshakil\CpanelWhm\Support\ConnectionConfig;
use Itxshakil\CpanelWhm\WhmRequest;

/**
 * Sends calls through Laravel's HTTP client, so Http::fake() and
 * Http::preventStrayRequests() work on it too.
 *
 * The token travels only in the Authorization header. POST bodies are
 * form-encoded (WHM reads parameters as form fields), which keeps passwords
 * out of URLs and therefore out of cURL error messages and server logs.
 */
final readonly class HttpTransport implements Transport
{
    public function __construct(private Factory $http) {}

    public function send(ConnectionConfig $config, WhmRequest $request): TransportResponse
    {
        $pending = $this->http->createPendingRequest()
            ->withHeaders(['Authorization' => "whm {$config->user}:{$config->token}"])
            ->acceptJson()
            ->timeout($request->timeout ?? $config->timeout)
            ->connectTimeout($config->connectTimeout);

        if (! $config->verifyTls) {
            $pending = $pending->withoutVerifying();
        }

        $url = $config->endpoint($request->function);
        $params = ['api.version' => 1, ...$request->params];
        $startedAt = hrtime(true);

        try {
            $response = $request->method === HttpMethod::Post
                ? $pending->asForm()->post($url, $params)
                : $pending->get($url, $params);
        } catch (ConnectionException $connectionException) {
            throw WhmConnectionFailed::to($config, $request->function, $connectionException);
        }

        $json = $response->json();

        return new TransportResponse(
            status: $response->status(),
            json: is_array($json) ? $json : null,
            body: $response->body(),
            durationMs: (hrtime(true) - $startedAt) / 1_000_000,
        );
    }
}
