<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Console;

use Illuminate\Console\Command;
use Itxshakil\CpanelWhm\Console\Concerns\InteractsWithWhm;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Support\CuratedFunctions;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'whm:functions')]
final class FunctionsCommand extends Command
{
    use InteractsWithWhm;

    protected $signature = 'whm:functions
        {search? : Only show functions containing this text}
        {--server : List the functions the server offers (applist) and mark which have a typed method}
        {--missing : With --server, only show functions without a typed method}
        {--connection= : The connection to use}';

    protected $description = 'List WHM API 1 functions and the typed method for each';

    public function handle(): int
    {
        $search = $this->argument('search');
        $search = is_string($search) ? mb_strtolower($search) : null;

        if ($this->option('server') !== true && $this->option('missing') !== true) {
            $rows = [];

            foreach (CuratedFunctions::MAP as $function => $method) {
                $rows[] = [$function, "Whm::{$method}"];
            }

            $this->table(['Function', 'Method'], $this->filter($rows, $search));
            $this->line('  <fg=gray>Any other function: Whm::call(\'function\', [...]) or php artisan whm:call function key=value</>');

            return self::SUCCESS;
        }

        try {
            $functions = $this->client()->server()->functions();
        } catch (WhmException $whmException) {
            return $this->reportFailure($whmException);
        }

        sort($functions);
        $rows = [];

        foreach ($functions as $function) {
            $typed = CuratedFunctions::has($function);

            if ($this->option('missing') === true && $typed) {
                continue;
            }

            $rows[] = [$function, $typed ? 'Whm::'.CuratedFunctions::MAP[$function] : "Whm::call('{$function}')"];
        }

        $rows = $this->filter($rows, $search);
        $this->table(['Function', 'Method'], $rows);

        $typedCount = count(array_filter($functions, CuratedFunctions::has(...)));
        $this->components->info(sprintf('%d functions on this server, %d with a typed method; every one is callable with Whm::call().', count($functions), $typedCount));

        return self::SUCCESS;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $rows
     * @return list<array{0: string, 1: string}>
     */
    private function filter(array $rows, ?string $search): array
    {
        if ($search === null || $search === '') {
            return $rows;
        }

        return array_values(array_filter($rows, static fn (array $row): bool => str_contains(mb_strtolower($row[0]), $search)));
    }
}
