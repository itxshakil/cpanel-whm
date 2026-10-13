<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Console;

use Illuminate\Console\Command;
use Itxshakil\CpanelWhm\Console\Concerns\InteractsWithWhm;
use Itxshakil\CpanelWhm\Data\ApiToken;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'whm:token')]
final class TokenCommand extends Command
{
    use InteractsWithWhm;

    protected $signature = 'whm:token
        {--days=14 : Warn about tokens that expire within this many days}
        {--connection= : The connection to use}';

    protected $description = 'List the WHM user\'s API tokens and warn about ones that expire soon (exits 1 when one does)';

    public function handle(): int
    {
        $days = max(0, (int) $this->stringOption('days'));

        try {
            $tokens = $this->client()->tokens()->list();
        } catch (WhmException $whmException) {
            return $this->reportFailure($whmException);
        }

        if ($tokens->isEmpty()) {
            $this->components->info('No API tokens.');

            return self::SUCCESS;
        }

        $this->table(
            ['Token', 'Created', 'Expires', 'Privileges', 'Allowed IPs'],
            $tokens->map(static fn (ApiToken $token): array => [
                $token->name,
                $token->createdAt?->toDateString() ?? '',
                match (true) {
                    ! $token->expires() => 'never',
                    $token->isExpired() => '<fg=red>expired '.$token->expiresAt?->toDateString().'</>',
                    $token->expiresWithin($days) => '<fg=yellow>'.$token->expiresAt?->toDateString().' ('.$token->daysLeft().' days)</>',
                    default => $token->expiresAt?->toDateString() ?? '',
                },
                mb_strimwidth(implode(', ', $token->privileges), 0, 50, '…'),
                $token->allowedIps === [] ? 'any' : implode(', ', $token->allowedIps),
            ])->all(),
        );

        // An expired token can't be the one in use (it just listed the tokens), so
        // it only gets a nudge to revoke it; failing on it would fail forever.
        $expired = $tokens->filter(static fn (ApiToken $token): bool => $token->isExpired());
        $expiring = $tokens->filter(static fn (ApiToken $token): bool => ! $token->isExpired() && $token->expiresWithin($days));

        if ($expired->isNotEmpty()) {
            $this->components->info($expired->count().' expired token(s): '.$expired->map(static fn (ApiToken $token): string => $token->name)->implode(', ').'. Revoke them in WHM › Manage API Tokens.');
        }

        if ($expiring->isNotEmpty()) {
            $this->components->warn($expiring->count()." token(s) expire within {$days} days. Create a new token in WHM › Manage API Tokens and update WHM_TOKEN.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
