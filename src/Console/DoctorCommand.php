<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Console;

use Illuminate\Console\Command;
use Itxshakil\CpanelWhm\Doctor\ConnectionReport;
use Itxshakil\CpanelWhm\Enums\CheckStatus;
use Itxshakil\CpanelWhm\WhmManager;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'whm:doctor', aliases: ['whm:test'])]
final class DoctorCommand extends Command
{
    protected $signature = 'whm:doctor
        {--connection= : The connection to check (default: the default connection)}
        {--all : Check every configured connection}
        {--json : Print the report as JSON}';

    protected $description = 'Check a WHM connection step by step and explain what to fix';

    /**
     * @var array<string>
     */
    protected $aliases = ['whm:test'];

    public function handle(WhmManager $whm): int
    {
        $names = $this->connectionNames($whm);
        $reports = array_map(static fn (string $name): ConnectionReport => $whm->ping($name), $names);

        if ($this->option('json') === true) {
            $this->line((string) json_encode(
                count($reports) === 1 ? $reports[0]->toArray() : array_map(static fn (ConnectionReport $report): array => $report->toArray(), $reports),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));
        } else {
            foreach ($reports as $report) {
                $this->render($report);
            }
        }

        foreach ($reports as $report) {
            if (! $report->passed()) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function connectionNames(WhmManager $whm): array
    {
        if ($this->option('all') === true) {
            $names = $whm->connectionNames();

            return $names === [] ? [$whm->getDefaultConnection()] : $names;
        }

        $connection = $this->option('connection');

        return [is_string($connection) && $connection !== '' ? $connection : $whm->getDefaultConnection()];
    }

    private function render(ConnectionReport $report): void
    {
        $this->newLine();
        $this->line("  <options=bold>WHM connection · {$report->connection}</> <fg=gray>· {$report->target}</>");
        $this->newLine();

        foreach ($report->checks as $check) {
            $colour = match ($check->status) {
                CheckStatus::Pass => 'green',
                CheckStatus::Warn => 'yellow',
                CheckStatus::Fail => 'red',
                CheckStatus::Skip => 'gray',
            };

            $this->line(sprintf('  <fg=%s>%s</> %-11s %s', $colour, $check->status->symbol(), $check->name, $check->message));

            if ($check->hint !== null && $check->status !== CheckStatus::Pass) {
                $this->line("    <fg=gray>→ {$check->hint}</>");
            }
        }

        $this->newLine();

        $failure = $report->firstFailure();

        if ($failure === null) {
            $this->components->info('The connection works.');

            return;
        }

        $this->components->error("{$failure->name} check failed.");
    }
}
