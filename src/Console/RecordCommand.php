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
        $directory = base_path(trim($this->stringOption('path'), '/'));
        $path = $directory.'/'.$name.'.json';

        $files->ensureDirectoryExists($directory);
        $files->put($path, json_encode(Redactor::redact($json), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

        $this->components->info("Saved {$path}");
        $this->line('  Replay it: <fg=gray>Whm::fake(["'.$function.'" => Whm::fixture(\''.$path.'\')]);</>');
        $this->line('  Check it for customer data before committing: only secrets are redacted.');

        return self::SUCCESS;
    }
}
