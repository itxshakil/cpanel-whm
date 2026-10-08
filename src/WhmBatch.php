<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm;

use Itxshakil\CpanelWhm\Contracts\WhmClient;
use Itxshakil\CpanelWhm\Exceptions\WhmCommandFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Support\Params;
use SensitiveParameter;

/**
 * Several WHM API 1 functions in one request, through WHM's batch function.
 *
 *     $results = Whm::batch()
 *         ->add('accountsummary', ['user' => 'acme'])
 *         ->add('showbw', ['searchtype' => 'user', 'search' => 'acme'])
 *         ->send();
 *
 *     $results[0]->get('acct.0.domain');
 *     $results->throw();   // the first failed command, if any
 *
 * The batch is one call that changes things as far as retries go: it is never
 * retried or cached, whatever its commands are.
 */
final class WhmBatch
{
    /**
     * @var list<array{function: string, params: array<string, mixed>}>
     */
    private array $commands = [];

    private bool $abortOnError = false;

    private ?int $timeout = null;

    public function __construct(private readonly WhmClient $client) {}

    /**
     * @param  array<string, mixed>  $params  normalised like Whm::call(): nulls left out, booleans as 1/0, lists repeated
     */
    public function add(string $function, #[SensitiveParameter] array $params = []): self
    {
        $this->commands[] = ['function' => $function, 'params' => Params::normalise($params)];

        return $this;
    }

    /**
     * Stop at the first command that fails; later commands are not run.
     */
    public function abortOnError(bool $abort = true): self
    {
        $this->abortOnError = $abort;

        return $this;
    }

    /**
     * Seconds to wait for the whole batch, when it needs longer than the connection's timeout.
     */
    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Send every command in one request. A command that fails does not throw
     * here: check $results->successful(), failures() or call throw().
     *
     * @throws WhmException when the batch request itself fails
     */
    public function send(): BatchResults
    {
        if ($this->commands === []) {
            return new BatchResults([], []);
        }

        $params = [
            'command' => array_map(self::encode(...), $this->commands),
            'abort_on_error' => $this->abortOnError,
        ];

        try {
            $response = $this->client->call('batch', $params, timeout: $this->timeout);
        } catch (WhmCommandFailed $whmCommandFailed) {
            // WHM may mark the whole batch failed when one command fails; the
            // per-command results are still there.
            if (! is_array($whmCommandFailed->response()->get('result'))) {
                throw $whmCommandFailed;
            }

            $response = $whmCommandFailed->response();
        }

        $results = $response->get('result');
        $responses = [];

        foreach (is_array($results) ? array_values($results) : [] as $result) {
            $responses[] = WhmResponse::fromArray(is_array($result) ? $result : []);
        }

        return new BatchResults($responses, array_column($this->commands, 'function'));
    }

    /**
     * "function?key=value&...", the form WHM's batch takes for each command.
     *
     * @param  array{function: string, params: array<string, mixed>}  $command
     */
    private static function encode(array $command): string
    {
        return $command['params'] === []
            ? $command['function']
            : $command['function'].'?'.http_build_query($command['params'], '', '&', PHP_QUERY_RFC3986);
    }
}
