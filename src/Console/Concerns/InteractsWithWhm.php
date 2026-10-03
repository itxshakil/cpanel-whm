<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Console\Concerns;

use Illuminate\Console\Command;
use Itxshakil\CpanelWhm\Contracts\WhmClient;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\WhmManager;

/**
 * @mixin Command
 */
trait InteractsWithWhm
{
    protected function whm(): WhmManager
    {
        return $this->laravel->make(WhmManager::class);
    }

    protected function client(): WhmClient
    {
        $connection = $this->option('connection');

        return $this->whm()->connection(is_string($connection) && $connection !== '' ? $connection : null);
    }

    protected function stringArgument(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? $value : '';
    }

    protected function stringOption(string $name): string
    {
        $value = $this->option($name);

        return is_string($value) ? $value : '';
    }

    /**
     * Print a WHM error and its hint, and return the failure exit code.
     */
    protected function reportFailure(WhmException $exception): int
    {
        $this->components->error($exception->getMessage());

        $hint = $exception->hint();

        if ($hint !== null) {
            $this->line("  <fg=gray>→ {$hint}</>");
            $this->newLine();
        }

        return self::FAILURE;
    }
}
