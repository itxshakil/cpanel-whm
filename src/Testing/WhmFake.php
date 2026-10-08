<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Testing;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use InvalidArgumentException;
use Itxshakil\CpanelWhm\Contracts\Transport;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Exceptions\StrayWhmCall;
use Itxshakil\CpanelWhm\Exceptions\WhmConnectionFailed;
use Itxshakil\CpanelWhm\Support\ConnectionConfig;
use Itxshakil\CpanelWhm\Transport\TransportResponse;
use Itxshakil\CpanelWhm\WhmRequest;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * The transport behind Whm::fake(). It answers with the responses you give it
 * and records every call. Answers still go through the real client, so faked
 * calls parse and fail exactly like real ones.
 */
final class WhmFake implements Transport
{
    /**
     * @var array<string, mixed>
     */
    private array $stubs = [];

    /**
     * @var list<WhmRequest>
     */
    private array $recorded = [];

    private bool $preventStrayCalls = true;

    /**
     * @param  array<string, mixed>  $responses  function name (or "*") => response
     */
    public function __construct(array $responses = [])
    {
        foreach ($responses as $function => $response) {
            $this->stub($function, $response);
        }
    }

    /**
     * A successful WHM answer: metadata.result = 1.
     *
     * @param  array<array-key, mixed>  $data
     * @param  array<string, mixed>  $metadata  extra metadata, e.g. ['output' => ['raw' => '...']]
     */
    public static function response(array $data = [], string $reason = 'OK', array $metadata = []): TransportResponse
    {
        return TransportResponse::json([
            'data' => $data,
            'metadata' => ['result' => 1, 'reason' => $reason, 'version' => 1, ...$metadata],
        ]);
    }

    /**
     * WHM ran the function and reported failure: metadata.result = 0.
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function failure(string $reason, array $data = []): TransportResponse
    {
        return TransportResponse::json([
            'data' => $data,
            'metadata' => ['result' => 0, 'reason' => $reason, 'version' => 1],
        ]);
    }

    /**
     * An HTTP error, e.g. 401 for a rejected token.
     */
    public static function httpError(int $status = 500, string $body = ''): TransportResponse
    {
        $json = json_decode($body, true);

        return new TransportResponse($status, is_array($json) ? $json : null, $body);
    }

    /**
     * The server could not be reached.
     */
    public static function connectionError(string $message = 'Connection refused'): FakeConnectionFailure
    {
        return new FakeConnectionFailure($message);
    }

    /**
     * A successful uapi_cpanel answer.
     *
     * @param  list<string>  $warnings
     * @param  list<string>  $messages
     */
    public static function uapi(mixed $data = [], array $warnings = [], array $messages = []): TransportResponse
    {
        return self::response(['uapi' => [
            'status' => 1,
            'data' => $data,
            'errors' => null,
            'warnings' => $warnings === [] ? null : $warnings,
            'messages' => $messages === [] ? null : $messages,
            'metadata' => [],
        ]]);
    }

    /**
     * uapi_cpanel itself worked but the UAPI function failed.
     *
     * @param  string|list<string>  $errors
     */
    public static function uapiFailure(string|array $errors): TransportResponse
    {
        return self::response(['uapi' => [
            'status' => 0,
            'data' => null,
            'errors' => is_string($errors) ? [$errors] : $errors,
            'warnings' => null,
            'messages' => null,
            'metadata' => [],
        ]]);
    }

    public static function sequence(mixed ...$responses): FakeSequence
    {
        return new FakeSequence(...$responses);
    }

    /**
     * A response saved with php artisan whm:record (a JSON file with data and
     * metadata). A relative path is resolved from the project root.
     */
    public static function fixture(string $path): TransportResponse
    {
        $resolved = str_starts_with($path, '/') || ! function_exists('base_path') ? $path : base_path($path);
        $contents = is_file($resolved) ? file_get_contents($resolved) : false;
        $json = is_string($contents) ? json_decode($contents, true) : null;

        if (! is_array($json)) {
            throw new InvalidArgumentException("{$path} is not a WHM fixture. Record one with php artisan whm:record.");
        }

        return TransportResponse::json($json);
    }

    /**
     * Set or replace the response for a function. Use "*" for any function.
     * The response may be a TransportResponse, a data array (a success), a
     * FakeSequence, a connection error, or a closure receiving the WhmRequest.
     */
    public function stub(string $function, mixed $response): self
    {
        $this->stubs[$function] = $response;

        return $this;
    }

    /**
     * Answer unknown functions with an empty success instead of failing the test.
     */
    public function allowStrayCalls(): self
    {
        $this->preventStrayCalls = false;

        return $this;
    }

    public function preventStrayCalls(bool $prevent = true): self
    {
        $this->preventStrayCalls = $prevent;

        return $this;
    }

    public function send(ConnectionConfig $config, WhmRequest $request): TransportResponse
    {
        // Without a stub for batch itself, each command is answered (and
        // recorded) as if it had been called on its own, then the batch.
        if ($request->function === 'batch' && ! isset($this->stubs['batch'])) {
            $response = $this->answerBatch($config, $request);
            $this->recorded[] = $request;

            return $this->withCommand($response, 'batch');
        }

        $this->recorded[] = $request;

        return $this->answer($config, $request);
    }

    private function answer(ConnectionConfig $config, WhmRequest $request): TransportResponse
    {
        $stub = $this->stubs[$request->function] ?? $this->stubs['*'] ?? null;

        if ($stub === null) {
            if ($this->preventStrayCalls) {
                throw StrayWhmCall::for($request->function);
            }

            return $this->withCommand(self::response(), $request->function);
        }

        return $this->withCommand($this->resolve($stub, $config, $request), $request->function);
    }

    /**
     * Every recorded call, or only those to one function.
     *
     * @return list<WhmRequest>
     */
    public function recorded(?string $function = null): array
    {
        if ($function === null) {
            return $this->recorded;
        }

        return array_values(array_filter($this->recorded, static fn (WhmRequest $request): bool => $request->function === $function));
    }

    /**
     * @param  (Closure(array<string, mixed>, WhmRequest): bool)|null  $callback
     */
    public function assertCalled(string $function, ?Closure $callback = null): self
    {
        PHPUnit::assertNotEmpty(
            $this->matching($function, $callback),
            $callback === null
                ? "Expected WHM function [{$function}] to be called, but it was not."
                : "Expected WHM function [{$function}] to be called with matching parameters, but no call matched.",
        );

        return $this;
    }

    /**
     * @param  (Closure(array<string, mixed>, WhmRequest): bool)|null  $callback
     */
    public function assertNotCalled(string $function, ?Closure $callback = null): self
    {
        PHPUnit::assertEmpty(
            $this->matching($function, $callback),
            "Expected WHM function [{$function}] not to be called, but it was.",
        );

        return $this;
    }

    public function assertCalledTimes(string $function, int $times): self
    {
        $count = count($this->recorded($function));

        PHPUnit::assertSame($times, $count, "Expected WHM function [{$function}] to be called {$times} time(s), but it was called {$count} time(s).");

        return $this;
    }

    public function assertSentCount(int $count): self
    {
        PHPUnit::assertCount($count, $this->recorded, "Expected {$count} WHM call(s), but ".count($this->recorded).' were made.');

        return $this;
    }

    public function assertNothingSent(): self
    {
        return $this->assertSentCount(0);
    }

    /**
     * @param  (Closure(array<string, mixed>, WhmRequest): bool)|null  $callback
     * @return list<WhmRequest>
     */
    private function matching(string $function, ?Closure $callback): array
    {
        return array_values(array_filter(
            $this->recorded($function),
            static fn (WhmRequest $request): bool => ! $callback instanceof Closure || $callback($request->params, $request),
        ));
    }

    private function resolve(mixed $stub, ConnectionConfig $config, WhmRequest $request): TransportResponse
    {
        if ($stub instanceof FakeSequence) {
            return $this->resolve($stub->next(), $config, $request);
        }

        if ($stub instanceof Closure) {
            return $this->resolve($stub($request), $config, $request);
        }

        if ($stub instanceof FakeConnectionFailure) {
            throw WhmConnectionFailed::to($config, $request->function, new ConnectionException($stub->message));
        }

        if ($stub instanceof TransportResponse) {
            return $stub;
        }

        if (is_array($stub)) {
            return self::response($stub);
        }

        return self::response();
    }

    private function answerBatch(ConnectionConfig $config, WhmRequest $request): TransportResponse
    {
        $commands = [];

        foreach ($request->params as $key => $value) {
            if (is_string($value) && preg_match('/^command(?:-(\d+))?$/', $key, $match) === 1) {
                $commands[(int) ($match[1] ?? 0)] = $value;
            }
        }

        ksort($commands);
        $abortOnError = in_array($request->params['abort_on_error'] ?? 0, [1, '1', true], true);
        $results = [];

        foreach ($commands as $command) {
            [$function, $query] = array_pad(explode('?', $command, 2), 2, '');
            $params = [];

            foreach (array_filter(explode('&', $query), static fn (string $pair): bool => $pair !== '') as $pair) {
                [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
                $params[rawurldecode($key)] = rawurldecode($value);
            }

            $inner = new WhmRequest($request->connection, $function, $params, HttpMethod::for($function, $params));
            $this->recorded[] = $inner;

            $answer = $this->answer($config, $inner);
            $json = $answer->json ?? ['data' => null, 'metadata' => ['command' => $function, 'result' => 0, 'reason' => "HTTP {$answer->status}"]];
            $results[] = $json;

            $result = is_array($json['metadata'] ?? null) ? ($json['metadata']['result'] ?? 0) : 0;

            if ($abortOnError && (int) (is_numeric($result) ? $result : 0) !== 1) {
                break;
            }
        }

        return self::response(['result' => $results]);
    }

    /**
     * Real WHM echoes the function name in metadata.command; fakes do too.
     */
    private function withCommand(TransportResponse $response, string $function): TransportResponse
    {
        $json = $response->json;

        if (! is_array($json) || ! is_array($json['metadata'] ?? null) || isset($json['metadata']['command'])) {
            return $response;
        }

        $json['metadata']['command'] = $function;

        return new TransportResponse($response->status, $json, (string) json_encode($json), $response->durationMs);
    }
}
