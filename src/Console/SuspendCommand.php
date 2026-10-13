<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Console;

use Illuminate\Console\Command;
use Itxshakil\CpanelWhm\Console\Concerns\InteractsWithWhm;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'whm:suspend')]
final class SuspendCommand extends Command
{
    use InteractsWithWhm;

    protected $signature = 'whm:suspend
        {user : The cPanel username}
        {--reason= : Why (shown in WHM and to the account\'s reseller)}
        {--lock : Stop the account\'s reseller from unsuspending it}
        {--force : Do not ask for confirmation}
        {--connection= : The connection to use}';

    protected $description = 'Suspend a cPanel account';

    public function handle(): int
    {
        $user = $this->stringArgument('user');

        // Without a terminal (cron, CI, -n) nobody can be asked, so only --force may
        // suspend; and the question itself defaults to no.
        if ($this->option('force') !== true && (! $this->input->isInteractive() || ! $this->confirm("Suspend {$user}? The sites and mail stop working.", false))) {
            $this->components->warn('Nothing changed. Pass --force to suspend without asking.');

            return self::FAILURE;
        }

        try {
            $this->client()->suspensions()->suspend($user, $this->stringOption('reason'), $this->option('lock') === true);
        } catch (WhmException $whmException) {
            return $this->reportFailure($whmException);
        }

        $this->components->info("{$user} is suspended.");

        return self::SUCCESS;
    }
}
