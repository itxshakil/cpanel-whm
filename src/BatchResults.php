<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Itxshakil\CpanelWhm\Exceptions\UapiCallFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmCommandFailed;
use LogicException;
use OutOfRangeException;
use Traversable;

/**
 * One WhmResponse per command of a WhmBatch, in the order they were added.
 * With abortOnError() there are fewer results than commands when one failed.
 *
 * @implements ArrayAccess<int, WhmResponse>
 * @implements IteratorAggregate<int, WhmResponse>
 */
final readonly class BatchResults implements ArrayAccess, Countable, IteratorAggregate
{
    /**
     * @param  list<WhmResponse>  $responses
     * @param  list<string>  $functions  the function of each command sent
     * @param  list<array<string, mixed>>  $params  the parameters of each command sent
     * @param  WhmResponse|null  $batch  WHM's answer to the batch call itself
     */
    public function __construct(
        private array $responses,
        private array $functions,
        private array $params = [],
        private ?WhmResponse $batch = null,
    ) {}

    public function get(int $index): WhmResponse
    {
        return $this->responses[$index] ?? throw new OutOfRangeException("The batch has no result {$index}.");
    }

    /**
     * @return list<WhmResponse>
     */
    public function all(): array
    {
        return $this->responses;
    }

    /**
     * Every command ran and succeeded.
     */
    public function successful(): bool
    {
        return $this->complete() && $this->failures() === [];
    }

    /**
     * Whether WHM answered every command (false after abortOnError stopped early).
     */
    public function complete(): bool
    {
        return count($this->responses) === count($this->functions);
    }

    /**
     * The commands that failed, keyed by their position in the batch. A
     * uapi_cpanel or cpanel command whose cPanel function failed counts too,
     * as UapiCallFailed, just like a single call.
     *
     * @return array<int, WhmCommandFailed|UapiCallFailed>
     */
    public function failures(): array
    {
        $failures = [];

        foreach ($this->responses as $index => $response) {
            $function = $response->command() ?? $this->functions[$index] ?? 'batch';

            if ($response->failed()) {
                $failures[$index] = WhmCommandFailed::classify($function, $response);

                continue;
            }

            $inner = UapiCallFailed::fromInnerCall($function, $this->params[$index] ?? [], $response);

            if ($inner instanceof UapiCallFailed) {
                $failures[$index] = $inner;
            }
        }

        return $failures;
    }

    /**
     * Throw the first failed command's exception, if any. A batch WHM cut
     * short without a failure throws too, since some commands never ran.
     *
     * @throws WhmCommandFailed
     * @throws UapiCallFailed
     */
    public function throw(): self
    {
        $failures = $this->failures();

        if ($failures !== []) {
            throw reset($failures);
        }

        if (! $this->complete()) {
            throw new WhmCommandFailed('batch', 'WHM answered '.count($this->responses).' of '.count($this->functions).' commands.', $this->batch ?? new WhmResponse);
        }

        return $this;
    }

    public function count(): int
    {
        return count($this->responses);
    }

    /**
     * @return Traversable<int, WhmResponse>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->responses);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->responses[$offset]);
    }

    public function offsetGet(mixed $offset): WhmResponse
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new LogicException('Batch results cannot be changed.');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new LogicException('Batch results cannot be changed.');
    }
}
