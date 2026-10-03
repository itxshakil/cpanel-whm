<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Itxshakil\CpanelWhm\Console\Concerns\InteractsWithWhm;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Support\CuratedFunctions;
use Itxshakil\CpanelWhm\Support\Redactor;
use Itxshakil\CpanelWhm\WhmResponse;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'whm:call')]
final class CallCommand extends Command
{
    use InteractsWithWhm;

    protected $signature = 'whm:call
        {function : The WHM API 1 function, e.g. listaccts}
        {params?* : Parameters as key=value pairs, e.g. user=acme}
        {--post : Send as a form POST (use it for passwords)}
        {--json : Print the raw response as JSON}
        {--dry-run : Show the request without sending it}
        {--force : Skip the confirmation for destructive functions}
        {--connection= : The connection to use}';

    protected $description = 'Call any WHM API 1 function and print the result';

    public function handle(): int
    {
        $function = $this->stringArgument('function');
        $params = $this->parseParams();
        $method = $this->option('post') === true ? HttpMethod::Post : HttpMethod::Get;

        try {
            $client = $this->client();
        } catch (WhmException $whmException) {
            return $this->reportFailure($whmException);
        }

        if ($this->option('dry-run') === true) {
            $config = $client->config();
            $query = http_build_query(['api.version' => 1, ...Redactor::redact($params)]);

            $this->line("{$method->value} {$config->endpoint($function)}?{$query}");
            $this->line("Authorization: whm {$config->user}:".Redactor::MASK);

            return self::SUCCESS;
        }

        if (CuratedFunctions::isDestructive($function) && $this->option('force') !== true && ! $this->confirmDestructive($function)) {
            $this->components->warn('Cancelled.');

            return self::FAILURE;
        }

        try {
            $response = $client->call($function, $params, $method);
        } catch (WhmException $whmException) {
            return $this->reportFailure($whmException);
        }

        $this->print($response);

        return self::SUCCESS;
    }

    private static function cell(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, string>
     */
    private function parseParams(): array
    {
        $params = [];
        /** @var array<int, string> $pairs */
        $pairs = (array) $this->argument('params');

        foreach ($pairs as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');

            if ($key !== '') {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    private function confirmDestructive(string $function): bool
    {
        $this->components->warn("{$function} destroys data and cannot be undone.");

        $answer = $this->ask("Type {$function} to confirm");

        return $answer === $function;
    }

    private function print(WhmResponse $response): void
    {
        $safe = Redactor::redact($response->toArray());

        if ($this->option('json') === true) {
            $this->line((string) json_encode($safe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return;
        }

        /** @var array<array-key, mixed> $data */
        $data = Redactor::redact($response->data);

        $rows = $this->listRows($data);

        if ($rows !== null) {
            $columns = array_slice(array_keys(array_merge(...$rows)), 0, 8);

            $this->table($columns, array_map(
                static fn (array $row): array => array_map(static fn (string|int $column): string => self::cell($row[$column] ?? ''), $columns),
                $rows,
            ));
        } elseif ($data !== []) {
            $this->table(['Key', 'Value'], array_map(
                static fn (string|int $key, mixed $value): array => [(string) $key, self::cell($value)],
                array_keys(Arr::dot($data)),
                Arr::dot($data),
            ));
        }

        foreach ($response->warnings() as $warning) {
            $this->components->warn($warning);
        }

        $this->components->info("{$response->command()}: {$response->reason()}");
    }

    /**
     * When data holds one list of records (listaccts' "acct", listpkgs' "pkg"),
     * print it as a table of rows.
     *
     * @param  array<array-key, mixed>  $data
     * @return list<array<array-key, mixed>>|null
     */
    private function listRows(array $data): ?array
    {
        $candidate = array_is_list($data) ? $data : (count($data) === 1 ? reset($data) : null);

        if (! is_array($candidate) || $candidate === [] || ! array_is_list($candidate)) {
            return null;
        }

        foreach ($candidate as $row) {
            if (! is_array($row)) {
                return null;
            }
        }

        /** @var list<array<array-key, mixed>> $candidate */
        return $candidate;
    }
}
