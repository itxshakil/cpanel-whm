<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Unit;

use Itxshakil\CpanelWhm\Contracts\WhmClient;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\WhmManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Whm::anything() reaches the manager or, through __call, the default client.
 * IDEs and static analysis only know about it from the facade's @method lines,
 * so every public method of both must be listed there.
 */
final class FacadeDocblockTest extends TestCase
{
    #[Test]
    public function every_public_manager_and_client_method_has_a_method_tag(): void
    {
        $facade = new ReflectionClass(Whm::class);
        preg_match_all('/@method\s+static\s+\S+\s+(\w+)\(/', (string) $facade->getDocComment(), $matches);
        $documented = $matches[1];

        // Defined on the facade itself, or plumbing that isn't reached through it.
        $skip = ['__construct', '__call', 'setContainer', 'fake'];

        $missing = [];

        foreach ([WhmManager::class, WhmClient::class] as $class) {
            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $name = $method->getName();

                if (! in_array($name, $skip, true) && ! in_array($name, $documented, true) && ! $facade->hasMethod($name)) {
                    $missing[] = "{$class}::{$name}()";
                }
            }
        }

        self::assertSame([], array_values(array_unique($missing)), 'Add @method lines to Facades\Whm for these.');
    }
}
