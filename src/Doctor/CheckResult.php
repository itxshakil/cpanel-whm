<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Doctor;

use Itxshakil\CpanelWhm\Enums\CheckStatus;

final readonly class CheckResult
{
    public function __construct(
        public string $name,
        public CheckStatus $status,
        public string $message,
        public ?string $hint = null,
    ) {}

    public static function pass(string $name, string $message): self
    {
        return new self($name, CheckStatus::Pass, $message);
    }

    public static function warn(string $name, string $message, ?string $hint = null): self
    {
        return new self($name, CheckStatus::Warn, $message, $hint);
    }

    public static function fail(string $name, string $message, ?string $hint = null): self
    {
        return new self($name, CheckStatus::Fail, $message, $hint);
    }

    public static function skip(string $name, string $message = 'skipped: an earlier check failed'): self
    {
        return new self($name, CheckStatus::Skip, $message);
    }

    /**
     * @return array{name: string, status: string, message: string, hint: ?string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status->value,
            'message' => $this->message,
            'hint' => $this->hint,
        ];
    }
}
