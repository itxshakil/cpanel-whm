<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Testing;

use OutOfBoundsException;

/**
 * Responses handed out one per call, in order: Whm::sequence(Whm::failure('busy'), Whm::response()).
 */
final class FakeSequence
{
    /**
     * @var list<mixed>
     */
    private array $responses;

    private mixed $whenEmpty = null;

    private bool $hasWhenEmpty = false;

    public function __construct(mixed ...$responses)
    {
        $this->responses = array_values($responses);
    }

    public function push(mixed $response): self
    {
        $this->responses[] = $response;

        return $this;
    }

    /**
     * What to return once the sequence runs out, instead of failing the test.
     */
    public function whenEmpty(mixed $response): self
    {
        $this->whenEmpty = $response;
        $this->hasWhenEmpty = true;

        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->responses === [];
    }

    public function next(): mixed
    {
        if ($this->responses !== []) {
            return array_shift($this->responses);
        }

        if ($this->hasWhenEmpty) {
            return $this->whenEmpty;
        }

        throw new OutOfBoundsException('A Whm::sequence() ran out of responses.');
    }
}
