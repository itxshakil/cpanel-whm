<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Exceptions;

use RuntimeException;

/**
 * Base class for every error this package throws. Catch it to handle all of them.
 *
 * Messages never contain the API token, passwords or request query strings.
 */
abstract class WhmException extends RuntimeException
{
    /**
     * What to do next, in one or two sentences, when there is something useful to say.
     */
    public function hint(): ?string
    {
        return null;
    }

    /**
     * A copy that is safe to hand to event listeners and logs. Exceptions that
     * carry no WHM output return themselves; the others mask secrets in it.
     */
    public function redacted(): static
    {
        return $this;
    }
}
