<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
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
     */
    public function __construct(
        private array $responses,
        private array $functions,
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
     * The commands that failed, keyed by their position in the batch.
     *
     * @return array<int, WhmCommandFailed>
     */
    public function failures(): array
    {
        $failures = [];

        foreach ($this->responses as $index => $response) {
            if ($response->failed()) {
                $failures[$index] = WhmCommandFailed::classify($response->command() ?? $this->functions[$index] ?? 'batch', $response);
            }
        }

        return $failures;
    }

    /**
     * Throw the first failed command's exception, if any.
     *
     * @throws WhmCommandFailed
     */
    public function throw(): self
    {
        $failures = $this->failures();

        if ($failures !== []) {
            throw reset($failures);
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
