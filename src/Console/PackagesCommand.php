<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Console;

use Illuminate\Console\Command;
use Itxshakil\CpanelWhm\Console\Concerns\InteractsWithWhm;
use Itxshakil\CpanelWhm\Data\Package;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'whm:packages')]
final class PackagesCommand extends Command
{
    use InteractsWithWhm;

    protected $signature = 'whm:packages
        {--json : Print JSON}
        {--connection= : The connection to use}';

    protected $description = 'List hosting packages and their limits';

    public function handle(): int
    {
        try {
            $packages = $this->client()->packages()->list();
        } catch (WhmException $whmException) {
            return $this->reportFailure($whmException);
        }

        if ($this->option('json') === true) {
            $this->line((string) json_encode($packages->map(static fn (Package $package): array => $package->raw)->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($packages->isEmpty()) {
            $this->components->info('No packages.');

            return self::SUCCESS;
        }

        $this->table(
            ['Package', 'Disk (MB)', 'Bandwidth (MB)', 'Addon', 'Sub', 'Email', 'DBs', 'FTP', 'Feature list'],
            $packages->map(static fn (Package $package): array => [
                $package->name,
                $package->diskQuota ?? '',
                $package->bandwidthLimit ?? '',
                $package->maxAddonDomains ?? '',
                $package->maxSubdomains ?? '',
                $package->maxEmailAccounts ?? '',
                $package->maxDatabases ?? '',
                $package->maxFtpAccounts ?? '',
                $package->featureList ?? '',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
