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
        {--connection= : The connection to use}';

    protected $description = 'Unsuspend a cPanel account';

    public function handle(): int
    {
        $user = $this->stringArgument('user');

        try {
            $this->client()->suspensions()->unsuspend($user);
        } catch (WhmException $whmException) {
            return $this->reportFailure($whmException);
        }

        $this->components->info("{$user} is active again.");

        return self::SUCCESS;
    }
}
