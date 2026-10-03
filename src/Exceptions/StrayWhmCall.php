<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Exceptions;

/**
 * Whm::fake() received a call it has no response for.
 */
final class StrayWhmCall extends WhmException
{
    public static function for(string $function): self
    {
        return new self("Unexpected WHM call to [{$function}]: Whm::fake() has no response for it.");
    }

    public function hint(): string
    {
        return "Add it to Whm::fake(['function' => Whm::response([...])]), or call ->allowStrayCalls() on the fake.";
    }
}
