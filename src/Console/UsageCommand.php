<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Number;
use Itxshakil\CpanelWhm\Console\Concerns\InteractsWithWhm;
use Itxshakil\CpanelWhm\Data\AccountUsage;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'whm:usage')]
final class UsageCommand extends Command
{
    use InteractsWithWhm;

    protected $signature = 'whm:usage
        {--threshold=90 : Flag accounts using at least this percent of their disk or bandwidth limit}
        {--all : List every account, not only the flagged ones}
        {--fresh : Read disk use live instead of from WHM\'s quota cache (slower)}
        {--json : Print the accounts as JSON}
        {--connection= : The connection to use}';

    protected $description = 'Accounts near their disk or bandwidth limit this month (exits 1 when any is at or above --threshold)';

    public function handle(): int
    {
        $option = $this->option('threshold');
        $threshold = is_numeric($option) ? max(0.0, (float) $option) : 90.0;
        $shownThreshold = self::number($threshold).'%';

        try {
            $usage = $this->client()->usage()->all($this->option('fresh') === true);
        } catch (WhmException $whmException) {
            return $this->reportFailure($whmException);
        }

        $flagged = $usage->filter(static fn (AccountUsage $account): bool => $account->isNearLimit($threshold))->values();
        $shown = $this->option('all') === true ? $usage : $flagged;

        if ($this->option('json') === true) {
            $this->line((string) json_encode($shown->map(self::toArray(...))->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $flagged->isEmpty() ? self::SUCCESS : self::FAILURE;
        }

        if ($shown->isNotEmpty()) {
            $this->table(
                ['Account', 'Domain', 'Disk', 'Disk %', 'Bandwidth', 'Bandwidth %'],
                $shown->map(static fn (AccountUsage $account): array => [
                    $account->user,
                    $account->domain() ?? '',
                    self::size($account->disk?->usedBytes, $account->disk?->limitBytes),
                    self::percent($account->disk?->percentUsed(), $threshold),
                    self::size($account->bandwidth?->usedBytes, $account->bandwidth?->limitBytes),
                    self::percent($account->bandwidth?->percentUsed(), $threshold),
                ])->all(),
            );
        }

        if ($flagged->isEmpty()) {
            $this->components->info("No account is at or above {$shownThreshold} of a limit.");

            return self::SUCCESS;
        }

        $this->components->warn($flagged->count()." account(s) at or above {$shownThreshold} of their disk or bandwidth limit.");

        return self::FAILURE;
    }

    /**
     * @return array{user: string, domain: string|null, disk_bytes: int|null, disk_limit_bytes: int|null, disk_percent: float|null, bandwidth_bytes: int|null, bandwidth_limit_bytes: int|null, bandwidth_percent: float|null}
     */
    private static function toArray(AccountUsage $account): array
    {
        return [
            'user' => $account->user,
            'domain' => $account->domain(),
            'disk_bytes' => $account->disk?->usedBytes,
            'disk_limit_bytes' => $account->disk?->limitBytes,
            'disk_percent' => $account->disk?->percentUsed(),
            'bandwidth_bytes' => $account->bandwidth?->usedBytes,
            'bandwidth_limit_bytes' => $account->bandwidth?->limitBytes,
            'bandwidth_percent' => $account->bandwidth?->percentUsed(),
        ];
    }

    private static function size(?int $used, ?int $limit): string
    {
        if ($used === null) {
            return '';
        }

        return self::bytes($used).' / '.($limit === null ? 'unlimited' : self::bytes($limit));
    }

    private static function bytes(int $bytes): string
    {
        return (string) Number::fileSize($bytes, $bytes < 1024 ? 0 : 1);
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1), '0'), '.');
    }

    private static function percent(?float $percent, float $threshold): string
    {
        if ($percent === null) {
            return '';
        }

        $text = self::number($percent).'%';

        return $percent >= $threshold ? "<fg=red>{$text}</>" : $text;
    }
}
