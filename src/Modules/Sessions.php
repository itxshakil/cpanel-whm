<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use Itxshakil\CpanelWhm\Data\LoginSession;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Enums\SessionService;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Support\UsernameRules;

/**
 * One-time login links: create_user_session.
 */
class Sessions extends Module
{
    /**
     * @param  string|null  $app  a cPanel app key to land in, e.g. "Email_Accounts" or "FileManager_Home"
     *
     * @throws WhmException
     */
    public function create(string $user, SessionService $service = SessionService::Cpanel, ?string $app = null, ?string $locale = null): LoginSession
    {
        $user = $service === SessionService::Webmail ? trim($user) : UsernameRules::normalise($user);

        $params = array_filter([
            'user' => $user,
            'service' => $service->value,
            'app' => $app,
            'locale' => $locale,
        ], static fn (?string $value): bool => $value !== null && $value !== '');

        return LoginSession::fromResponse($this->client->call('create_user_session', $params, HttpMethod::Post), $user, $service);
    }
}
