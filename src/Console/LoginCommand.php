<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Itxshakil\CpanelWhm\Console\Concerns\InteractsWithWhm;
use Itxshakil\CpanelWhm\Enums\SessionService;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'whm:login')]
final class LoginCommand extends Command
{
    use InteractsWithWhm;

    protected $signature = 'whm:login
        {user : The cPanel username (or email address for webmail)}
        {--service=cpanel : cpanel, whm or webmail}
        {--app= : A cPanel app to open, e.g. Email_Accounts}
        {--connection= : The connection to use}';

    protected $description = 'Print a one-time login link for a cPanel account';

    public function handle(): int
    {
        try {
            $service = SessionService::fromName($this->stringOption('service'));
        } catch (InvalidArgumentException $invalidArgumentException) {
            $this->components->error($invalidArgumentException->getMessage());

            return self::FAILURE;
        }

        $app = $this->option('app');

        try {
            $session = $this->client()->sessions()->create(
                $this->stringArgument('user'),
                $service,
                is_string($app) && $app !== '' ? $app : null,
            );
        } catch (WhmException $whmException) {
            return $this->reportFailure($whmException);
        }

        $this->line($session->url);
        $this->newLine();
        $this->line('  <fg=gray>One use only. The session ends after 15 minutes without activity.</>');

        return self::SUCCESS;
    }
}
