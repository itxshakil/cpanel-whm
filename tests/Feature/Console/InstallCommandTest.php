<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature\Console;

use Illuminate\Filesystem\Filesystem;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class InstallCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/cpanel-whm-'.bin2hex(random_bytes(4));
        (new Filesystem)->ensureDirectoryExists($this->directory);
        file_put_contents($this->directory.'/.env', "APP_NAME=Test\nWHM_HOST=old.example.com\n");
        $this->app->useEnvironmentPath($this->directory);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->directory);
        @unlink(config_path('cpanel-whm.php'));

        parent::tearDown();
    }

    #[Test]
    public function it_writes_env_publishes_the_config_and_runs_the_doctor(): void
    {
        Whm::fake(['version' => ['version' => '11.134.0.5'], 'myprivs' => ['privileges' => [['all' => 1]]], 'applist' => ['app' => []]]);

        $this->artisan('whm:install')
            ->expectsQuestion('WHM host', 'new.example.com')
            ->expectsQuestion('WHM username', 'root')
            ->expectsQuestion('API token', 'TOKEN with space$')
            ->expectsConfirmation('Verify the TLS certificate?', 'yes')
            ->expectsOutputToContain('The connection works.')
            ->assertSuccessful();

        $env = (string) file_get_contents($this->directory.'/.env');

        self::assertStringContainsString("APP_NAME=Test\n", $env);
        self::assertStringContainsString('WHM_HOST=new.example.com', $env);
        self::assertStringNotContainsString('old.example.com', $env);
        self::assertStringContainsString('WHM_TOKEN="TOKEN with space\$"', $env);
        self::assertStringContainsString('WHM_VERIFY_TLS=true', $env);
        self::assertFileExists(config_path('cpanel-whm.php'));
        self::assertSame('new.example.com', config('cpanel-whm.connections.main.host'));
    }

    #[Test]
    public function the_doctor_can_be_skipped(): void
    {
        $this->artisan('whm:install', ['--no-doctor' => true])
            ->expectsQuestion('WHM host', 'new.example.com')
            ->expectsQuestion('WHM username', 'reseller')
            ->expectsQuestion('API token', 'abc')
            ->expectsConfirmation('Verify the TLS certificate?', 'no')
            ->assertSuccessful();

        $env = (string) file_get_contents($this->directory.'/.env');

        self::assertStringContainsString('WHM_USER=reseller', $env);
        self::assertStringContainsString('WHM_VERIFY_TLS=false', $env);
    }
}
