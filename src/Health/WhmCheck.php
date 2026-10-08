<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Health;

use Itxshakil\CpanelWhm\Data\ApiToken;
use Itxshakil\CpanelWhm\Exceptions\InvalidConfiguration;
use Itxshakil\CpanelWhm\Exceptions\WhmAuthenticationFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmConnectionFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Exceptions\WhmHttpError;
use Itxshakil\CpanelWhm\Exceptions\WhmPermissionDenied;
use Itxshakil\CpanelWhm\WhmManager;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * A spatie/laravel-health check for a WHM connection: the token works, the
 * server answers in time, and (optionally) the token is not about to expire.
 *
 *     Health::checks([
 *         WhmCheck::new()->warnWhenSlowerThan(1500)->failWhenTokenExpiresWithin(7),
 *         WhmCheck::new()->connection('ca-1')->name('WHM ca-1'),
 *     ]);
 *
 * Needs spatie/laravel-health (composer require spatie/laravel-health).
 */
class WhmCheck extends Check
{
    protected ?string $connection = null;

    protected int $warnAfterMs = 2000;

    protected ?int $tokenDays = null;

    public function connection(string $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    public function warnWhenSlowerThan(int $milliseconds): static
    {
        $this->warnAfterMs = $milliseconds;

        return $this;
    }

    /**
     * Also read api_token_list and fail when a token of the connection's user
     * expires within $days. Needs the token to be allowed to list tokens.
     */
    public function failWhenTokenExpiresWithin(int $days): static
    {
        $this->tokenDays = $days;

        return $this;
    }

    public function getName(): string
    {
        return $this->name ?? 'WHM'.($this->connection === null ? '' : " ({$this->connection})");
    }

    public function run(): Result
    {
        $manager = app(WhmManager::class);
        $connection = $this->connection ?? $manager->getDefaultConnection();
        $result = Result::make()->meta(['connection' => $connection]);

        $started = hrtime(true);

        try {
            // One attempt: a retry would hide a flaky server and stretch the timing.
            $client = $manager->connection($connection)->withoutRetries();
            $version = $client->server()->version();
            $elapsedMs = (int) round((hrtime(true) - $started) / 1_000_000);

            $result->appendMeta(['version' => $version, 'response_ms' => $elapsedMs]);

            if ($this->tokenDays !== null) {
                $expiring = $client->tokens()->expiringWithin($this->tokenDays);

                if ($expiring->isNotEmpty()) {
                    $names = $expiring->map(static fn (ApiToken $token): string => $token->name)->implode(', ');

                    return $result->shortSummary('Token expiring')
                        ->failed("WHM API token(s) {$names} expire within {$this->tokenDays} days.");
                }
            }
        } catch (WhmException $whmException) {
            return $result->shortSummary(self::summaryFor($whmException))
                ->failed(trim($whmException->getMessage().' '.($whmException->hint() ?? '')));
        }

        if ($elapsedMs > $this->warnAfterMs) {
            return $result->shortSummary("{$elapsedMs} ms")
                ->warning("WHM answered in {$elapsedMs} ms (more than {$this->warnAfterMs} ms).");
        }

        return $result->shortSummary("{$version}, {$elapsedMs} ms")->ok();
    }

    private static function summaryFor(WhmException $exception): string
    {
        return match (true) {
            $exception instanceof WhmAuthenticationFailed => 'Token rejected',
            $exception instanceof WhmPermissionDenied => 'Missing privilege',
            $exception instanceof WhmConnectionFailed => 'Unreachable',
            $exception instanceof WhmHttpError => "HTTP {$exception->status()}",
            $exception instanceof InvalidConfiguration => 'Not configured',
            default => 'WHM error',
        };
    }
}
