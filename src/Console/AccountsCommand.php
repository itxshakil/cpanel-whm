<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Console;

use Illuminate\Console\Command;
use Itxshakil\CpanelWhm\Console\Concerns\InteractsWithWhm;
use Itxshakil\CpanelWhm\Data\Account;
use Itxshakil\CpanelWhm\Enums\SearchType;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'whm:accounts')]
final class AccountsCommand extends Command
{
    use InteractsWithWhm;

    protected $signature = 'whm:accounts
        {--search= : Text to search for}
        {--by=user : What to search: user, domain, owner, ip or package}
        {--package= : Only accounts on this package}
        {--suspended : Only suspended accounts}
        {--connection= : The connection to use}';

    protected $description = 'List cPanel accounts';

    public function handle(): int
    {
        $search = $this->option('search');
        $package = $this->option('package');
        $by = SearchType::tryFrom($this->stringOption('by'));

        if (! $by instanceof SearchType) {
            $this->components->error('--by must be one of: user, domain, owner, ip, package.');

            return self::FAILURE;
        }

        try {
            $accounts = match (true) {
                is_string($package) && $package !== '' => $this->client()->accounts()->search($package, SearchType::Package, exact: true),
                is_string($search) && $search !== '' => $this->client()->accounts()->search($search, $by),
                default => $this->client()->accounts()->list(),
            };
        } catch (WhmException $whmException) {
            return $this->reportFailure($whmException);
        }

        if ($this->option('suspended') === true) {
            $accounts = $accounts->filter(static fn (Account $account): bool => $account->suspended)->values();
        }

        if ($accounts->isEmpty()) {
            $this->components->info('No accounts found.');

            return self::SUCCESS;
        }

        $this->table(
            ['User', 'Domain', 'Package', 'IP', 'Disk', 'Suspended'],
            $accounts->map(static fn (Account $account): array => [
                $account->username,
                $account->domain,
                $account->package ?? '',
                $account->ip ?? '',
                trim(($account->diskUsed ?? '').' / '.($account->diskLimit ?? ''), ' /'),
                $account->suspended ? 'yes'.($account->suspendReason !== null ? ": {$account->suspendReason}" : '') : '',
            ])->all(),
        );

        $this->components->info("{$accounts->count()} account(s).");

        return self::SUCCESS;
    }
}
