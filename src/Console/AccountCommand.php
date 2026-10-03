<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Console;

use Illuminate\Console\Command;
use Itxshakil\CpanelWhm\Console\Concerns\InteractsWithWhm;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'whm:account')]
final class AccountCommand extends Command
{
    use InteractsWithWhm;

    protected $signature = 'whm:account
        {user : The cPanel username}
        {--connection= : The connection to use}';

    protected $description = "Show one cPanel account's summary";

    public function handle(): int
    {
        $user = $this->stringArgument('user');

        try {
            $account = $this->client()->accounts()->find($user);
        } catch (WhmException $whmException) {
            return $this->reportFailure($whmException);
        }

        if ($account === null) {
            $this->components->error("Account [{$user}] does not exist.");

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('<options=bold>User</>', $account->username);
        $this->components->twoColumnDetail('Domain', $account->domain);
        $this->components->twoColumnDetail('Package', $account->package ?? '—');
        $this->components->twoColumnDetail('IP', $account->ip ?? '—');
        $this->components->twoColumnDetail('Contact email', $account->email ?? '—');
        $this->components->twoColumnDetail('Owner', $account->owner ?? '—');
        $this->components->twoColumnDetail('Disk', trim(($account->diskUsed ?? '?').' of '.($account->diskLimit ?? '?')));
        $this->components->twoColumnDetail('Created', $account->createdAt?->toDateString() ?? '—');
        $this->components->twoColumnDetail(
            'Suspended',
            $account->suspended ? '<fg=red>yes</>'.($account->suspendReason !== null ? " ({$account->suspendReason})" : '') : 'no',
        );

        return self::SUCCESS;
    }
}
