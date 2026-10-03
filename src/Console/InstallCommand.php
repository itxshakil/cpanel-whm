<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Itxshakil\CpanelWhm\Exceptions\InvalidConfiguration;
use Itxshakil\CpanelWhm\Support\ConnectionConfig;
use Itxshakil\CpanelWhm\WhmManager;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'whm:install')]
final class InstallCommand extends Command
{
    protected $signature = 'whm:install
        {--no-doctor : Do not run whm:doctor afterwards}';

    protected $description = 'Connect this app to a WHM server: writes .env, publishes the config and checks the connection';

    public function handle(Filesystem $files, WhmManager $whm, Repository $config): int
    {
        $this->components->info('Connect to WHM. Create an API token in WHM > Development > Manage API Tokens first.');

        $host = text(
            label: 'WHM host',
            placeholder: 'server.example.com',
            required: true,
            validate: static function (string $value): ?string {
                try {
                    ConnectionConfig::fromArray('main', ['host' => $value, 'token' => 'x']);
                } catch (InvalidConfiguration $invalidConfiguration) {
                    return $invalidConfiguration->getMessage().' '.$invalidConfiguration->hint();
                }

                return null;
            },
            hint: 'A hostname (https and port 2087 are assumed) or a full URL.',
        );

        $user = text(label: 'WHM username', default: 'root', required: true, hint: 'The user the token belongs to: root or a reseller.');
        $token = password(label: 'API token', required: true, hint: 'Stored in .env as WHM_TOKEN.');
        $verifyTls = confirm(label: 'Verify the TLS certificate?', default: true, hint: 'Say no only for a server reached by IP with a self-signed certificate.');

        $this->writeEnvironment($files, [
            'WHM_HOST' => trim($host),
            'WHM_USER' => trim($user),
            'WHM_TOKEN' => trim($token),
            'WHM_VERIFY_TLS' => $verifyTls ? 'true' : 'false',
        ]);

        $this->components->task('Writing WHM_HOST, WHM_USER, WHM_TOKEN and WHM_VERIFY_TLS to .env');

        if (! $files->exists($this->laravel->configPath('cpanel-whm.php'))) {
            $this->callSilently('vendor:publish', ['--tag' => 'cpanel-whm-config']);
            $this->components->task('Publishing config/cpanel-whm.php');
        }

        $connection = $whm->getDefaultConnection();

        $current = $config->get("cpanel-whm.connections.{$connection}", []);

        $config->set("cpanel-whm.connections.{$connection}", [
            ...(is_array($current) ? $current : []),
            'host' => trim($host),
            'user' => trim($user),
            'token' => trim($token),
            'verify_tls' => $verifyTls,
        ]);
        $whm->purge();

        if ($this->option('no-doctor') === true) {
            return self::SUCCESS;
        }

        return $this->call('whm:doctor', ['--connection' => $connection]);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function writeEnvironment(Filesystem $files, array $values): void
    {
        $path = $this->laravel->environmentFilePath();
        $contents = $files->exists($path) ? $files->get($path) : '';

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->quote($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            $contents = preg_match($pattern, $contents) === 1
                ? (string) preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $contents)
                : rtrim($contents, "\n").($contents === '' ? '' : "\n").$line."\n";
        }

        $files->put($path, $contents);
    }

    private function quote(string $value): string
    {
        return preg_match('/[\s#"\'\\\\$]/', $value) === 1 ? '"'.addcslashes($value, '"\\$').'"' : $value;
    }
}
