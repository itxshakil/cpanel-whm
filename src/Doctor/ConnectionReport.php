<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Doctor;

use Itxshakil\CpanelWhm\Enums\CheckStatus;

/**
 * The outcome of whm:doctor / Whm::ping() for one connection.
 */
final readonly class ConnectionReport
{
    /**
     * @param  list<CheckResult>  $checks
     */
    public function __construct(
        public string $connection,
        public string $target,
        public array $checks,
    ) {}

    /**
     * True when no check failed. Warnings do not fail the report.
     */
    public function passed(): bool
    {
        return $this->firstFailure() === null;
    }

    public function firstFailure(): ?CheckResult
    {
        foreach ($this->checks as $check) {
            if ($check->status === CheckStatus::Fail) {
                return $check;
            }
        }

        return null;
    }

    public function check(string $name): ?CheckResult
    {
        foreach ($this->checks as $check) {
            if ($check->name === $name) {
                return $check;
            }
        }

        return null;
    }

    /**
     * @return array{connection: string, target: string, passed: bool, checks: list<array{name: string, status: string, message: string, hint: ?string}>}
     */
    public function toArray(): array
    {
        return [
            'connection' => $this->connection,
            'target' => $this->target,
            'passed' => $this->passed(),
            'checks' => array_map(static fn (CheckResult $check): array => $check->toArray(), $this->checks),
        ];
    }
}
