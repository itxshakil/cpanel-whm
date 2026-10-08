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
        {params?* : Parameters as key=value pairs, e.g. user=acme; give a secret as a bare key (password) to be asked for it}
        {--post : Send as a form POST (the default for functions that change something and for secrets)}
        {--json : Print the raw response as JSON}
        {--dry-run : Show the request without sending it}
        {--force : Skip the confirmation for destructive functions}
        {--connection= : The connection to use}';

    protected $description = 'Call any WHM API 1 function and print the result';

    public function handle(): int
    {
        $function = $this->stringArgument('function');
        $params = $this->parseParams();

        if ($params === null) {
            return self::FAILURE;
        }
        $method = HttpMethod::for($function, $params, $this->option('post') === true ? HttpMethod::Post : null);

        try {
            $client = $this->client();
        } catch (WhmException $whmException) {
            return $this->reportFailure($whmException);
        }

        if ($this->option('dry-run') === true) {
            $config = $client->config();
            $query = http_build_query(['api.version' => 1, ...Redactor::redact($params)]);

            if ($method === HttpMethod::Post) {
                $this->line("POST {$config->endpoint($function)}");
                $this->line("Authorization: whm {$config->user}:".Redactor::MASK);
                $this->line("Body: {$query}");
            } else {
                $this->line("GET {$config->endpoint($function)}?{$query}");
                $this->line("Authorization: whm {$config->user}:".Redactor::MASK);
            }

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
     * key=value pairs. A secret given as a bare key (password) is asked for
     * with hidden input, so it stays out of shell history and the process list.
     *
     * @return array<string, string>|null null when a secret could not be asked for
     */
    private function parseParams(): ?array
    {
        $params = [];
        $typedSecrets = [];
        /** @var array<int, string> $pairs */
        $pairs = (array) $this->argument('params');

        foreach ($pairs as $pair) {
            $hasValue = str_contains($pair, '=');
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');

            if ($key === '') {
                continue;
            }

            if (! Redactor::isSensitiveKey($key)) {
                $params[$key] = $value;

                continue;
            }

            if ($hasValue) {
                $typedSecrets[] = $key;
                $params[$key] = $value;

                continue;
            }

            if (! $this->input->isInteractive()) {
                $this->components->error("{$key} needs a value: give it as {$key}=... or run without --no-interaction to be asked for it.");

                return null;
            }

            $answer = $this->secret("Value for {$key}");
            $params[$key] = is_string($answer) ? $answer : '';
        }

        if ($typedSecrets !== []) {
            $keys = implode(', ', $typedSecrets);
            $this->components->warn("{$keys} was typed on the command line, where shell history and the process list keep it. Give it as a bare key ({$typedSecrets[0]}) to be asked for it instead.");
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
