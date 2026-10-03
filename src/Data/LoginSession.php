<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

use Carbon\CarbonImmutable;
use Itxshakil\CpanelWhm\Enums\SessionService;
use Itxshakil\CpanelWhm\Support\Redactor;
use Itxshakil\CpanelWhm\WhmResponse;

/**
 * A one-time login link from create_user_session. The session ends after
 * 15 minutes without activity.
 */
final readonly class LoginSession
{
    public function __construct(
        public string $url,
        public string $user,
        public SessionService $service,
        public ?string $session,
        public ?string $securityToken,
        public ?CarbonImmutable $expiresAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'url' => Redactor::redactString($this->url),
            'user' => $this->user,
            'service' => $this->service->value,
            'expiresAt' => $this->expiresAt?->toIso8601String(),
        ];
    }

    public static function fromResponse(WhmResponse $response, string $user, SessionService $service): self
    {
        return new self(
            url: Value::string($response->get('url')) ?? '',
            user: $user,
            service: $service,
            session: Value::string($response->get('session')),
            securityToken: Value::string($response->get('cp_security_token')),
            expiresAt: Value::timestamp($response->get('expires')),
        );
    }
}
