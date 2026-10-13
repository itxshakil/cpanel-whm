<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Itxshakil\CpanelWhm\Console\Concerns\InteractsWithWhm;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Exceptions\WhmCommandFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Support\FunctionCatalog;
use Itxshakil\CpanelWhm\Support\Redactor;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'whm:record')]
final class RecordCommand extends Command
{
    use InteractsWithWhm;

    protected $signature = 'whm:record
        {function : The WHM API 1 function, e.g. listaccts}
        {params?* : Parameters as key=value}
        {--path=tests/Fixtures/whm : Where to save, relative to the project}
        {--name= : File name without .json (default: the function name)}
        {--connection= : The connection to use}';

    protected $description = 'Call a read-only WHM function and save its redacted response as a JSON fixture for Whm::fake()';

    public function handle(Filesystem $files): int
    {
        $function = $this->stringArgument('function');

        if (! FunctionCatalog::has($function)) {
            $this->components->error("{$function} is not a documented WHM API 1 function, so whm:record cannot tell whether it changes the server.");
            $this->line('  <fg=gray>→ Inspect it with php artisan whm:call '.$function.' --json instead.</>');

            return self::FAILURE;
        }

        if (! FunctionCatalog::isReadOnly($function)) {
            $this->components->error("{$function} changes the server. whm:record only calls read-only functions.");

            return self::FAILURE;
        }

        // The fixture must land inside the project: --name=../../composer would
        // otherwise overwrite composer.json, and an absolute --path was quietly
        // turned into a relative one.
        $nameOption = $this->stringOption('name');
        $pathOption = $this->stringOption('path');

        if ($nameOption !== '' && (preg_match('/^[A-Za-z0-9._-]+$/', $nameOption) !== 1 || str_contains($nameOption, '..'))) {
            $this->components->error('--name is a file name: letters, digits, dots, dashes and underscores, without a path.');

            return self::FAILURE;
        }

        if (preg_match('#^(?:[/\\\\]|[A-Za-z]:)#', $pathOption) === 1 || preg_match('#(?:^|[/\\\\])\.\.(?:[/\\\\]|$)#', $pathOption) === 1) {
            $this->components->error('--path is a directory inside the project, such as tests/Fixtures/whm: no absolute paths or "..".');

            return self::FAILURE;
        }

        $params = [];

        foreach ((array) $this->argument('params') as $pair) {
            if (str_contains($pair, '=')) {
                [$key, $value] = explode('=', $pair, 2);
                $params[$key] = $value;
            }
        }

        try {
            $json = $this->client()->call($function, $params, HttpMethod::Get)->toArray();
            $json = ['data' => $json['data'], 'metadata' => $json['metadata']];
        } catch (WhmCommandFailed $whmCommandFailed) {
            // A failure is worth recording too: Whm::fake() can replay it.
            $json = ['data' => $whmCommandFailed->response()->data, 'metadata' => $whmCommandFailed->response()->metadata];
        } catch (WhmException $whmException) {
            return $this->reportFailure($whmException);
        }

        $name = $this->stringOption('name') !== '' ? $this->stringOption('name') : str_replace('/', '_', $function);
        // The replay hint uses the path relative to the project root, so it can be committed as is.
        $relative = trim($this->stringOption('path'), '/').'/'.$name.'.json';
        $directory = base_path(trim($this->stringOption('path'), '/'));
        $path = base_path($relative);

        $files->ensureDirectoryExists($directory);
        $files->put($path, json_encode(Redactor::redact($json), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

        $this->components->info("Saved {$path}");
        $this->line('  Replay it: <fg=gray>Whm::fake(["'.$function.'" => Whm::fixture(\''.$relative.'\')]);</>');
        $this->line('  Check it for customer data before committing: only secrets are redacted.');

        return self::SUCCESS;
    }
}
