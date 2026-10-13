<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Console;

use Illuminate\Console\Command;
use Itxshakil\CpanelWhm\Console\Concerns\InteractsWithWhm;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'whm:unsuspend')]
final class UnsuspendCommand extends Command
{
    use InteractsWithWhm;

    protected $signature = 'whm:unsuspend
        {user : The cPanel username}
        {--force : Do not ask for confirmation}
        {--connection= : The connection to use}';

    protected $description = 'Unsuspend a cPanel account';

    public function handle(): int
    {
        $user = $this->stringArgument('user');

        // An account may be suspended for abuse; bringing it back deserves a question too.
        if ($this->option('force') !== true && (! $this->input->isInteractive() || ! $this->confirm("Unsuspend {$user}? Its sites and mail start working again.", false))) {
            $this->components->warn('Nothing changed. Pass --force to unsuspend without asking.');

            return self::FAILURE;
        }

        try {
            $this->client()->suspensions()->unsuspend($user);
        } catch (WhmException $whmException) {
            return $this->reportFailure($whmException);
        }

        $this->components->info("{$user} is active again.");

        return self::SUCCESS;
    }
}
