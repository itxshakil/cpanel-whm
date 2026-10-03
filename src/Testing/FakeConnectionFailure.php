<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Testing;

/**
 * Marks a faked call that should fail as if the server were unreachable.
 */
final readonly class FakeConnectionFailure
{
    public function __construct(public string $message = 'Connection refused') {}
}
