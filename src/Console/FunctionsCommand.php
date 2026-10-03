<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Console;

use Illuminate\Console\Command;
use Itxshakil\CpanelWhm\Console\Concerns\InteractsWithWhm;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Support\CuratedFunctions;
use Itxshakil\CpanelWhm\Support\FunctionCatalog;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'whm:functions')]
final class FunctionsCommand extends Command
{
    use InteractsWithWhm;

    protected $signature = 'whm:functions
        {search? : Only functions whose name or summary contains this text}
        {--curated : Only functions with a hand-written method and typed results}
        {--uapi : List UAPI functions (run with Whm::asUser($user)) instead of WHM API 1}
        {--server : Compare with the functions this server offers (applist)}
        {--missing : With --server, only functions without a typed method}
        {--connection= : The connection to use}';

    protected $description = 'Find the method for a WHM API 1 or UAPI function';

    public function handle(): int
    {
        $search = mb_strtolower($this->stringArgument('search'));

        if ($this->option('uapi') === true) {
            return $this->listUapi($search);
        }

        if ($this->option('server') === true || $this->option('missing') === true) {
            return $this->compareWithServer($search);
        }

        $rows = [];

        foreach (FunctionCatalog::WHM as $function => [$generated, $summary]) {
            $curated = CuratedFunctions::MAP[$function] ?? null;

            if ($this->option('curated') === true && $curated === null) {
                continue;
            }

            $rows[] = [$function, $this->methodFor($function, $generated), $summary];
        }

        $rows = $this->filter($rows, $search);
        $this->table(['Function', 'Method', 'What it does'], $rows);
        $this->components->info(count($rows).' function(s). Hand-written methods return typed objects; Whm::api() methods return the checked WhmResponse.');

        return self::SUCCESS;
    }

    private function listUapi(string $search): int
    {
        $rows = [];

        foreach (FunctionCatalog::UAPI as $key => [$generated, $summary]) {
            $rows[] = [$key, $generated === null ? "uapi('".str_replace('::', "', '", $key)."')" : 'asUser($user)->api()->'.$generated, $summary];
        }

        $rows = $this->filter($rows, $search);
        $this->table(['Module::function', 'Method', 'What it does'], $rows);
        $this->components->info(count($rows).' UAPI function(s). Run them as an account: Whm::asUser(\'acme\')->api()->...');

        return self::SUCCESS;
    }

    private function compareWithServer(string $search): int
    {
        try {
            $functions = $this->client()->server()->functions();
        } catch (WhmException $whmException) {
            return $this->reportFailure($whmException);
        }

        sort($functions);
        $rows = [];
        $typed = 0;

        foreach ($functions as $function) {
            $entry = FunctionCatalog::WHM[$function] ?? null;
            $method = $this->methodFor($function, $entry[0] ?? null);
            $hasMethod = ! str_starts_with($method, 'Whm::call(');
            $typed += $hasMethod ? 1 : 0;

            if ($this->option('missing') === true && $hasMethod) {
                continue;
            }

            $rows[] = [$function, $method, $entry[1] ?? '(not in cPanel\'s published spec)'];
        }

        $this->table(['Function', 'Method', 'What it does'], $this->filter($rows, $search));
        $this->components->info(sprintf('%d functions on this server, %d with a typed method; every one is callable with Whm::call().', count($functions), $typed));

        return self::SUCCESS;
    }

    private function methodFor(string $function, ?string $generated): string
    {
        return match (true) {
            CuratedFunctions::has($function) => 'Whm::'.CuratedFunctions::MAP[$function],
            $generated !== null => 'Whm::api()->'.$generated,
            default => "Whm::call('{$function}')",
        };
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $rows
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function filter(array $rows, string $search): array
    {
        if ($search === '') {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => str_contains(mb_strtolower($row[0]), $search) || str_contains(mb_strtolower($row[2]), $search),
        ));
    }
}
