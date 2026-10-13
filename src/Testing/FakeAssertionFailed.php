<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Testing;

use RuntimeException;

/**
 * A WhmFake assertion failed where PHPUnit isn't installed (another test
 * runner, or a script). Under PHPUnit, assertions fail as usual instead.
 */
final class FakeAssertionFailed extends RuntimeException {}
