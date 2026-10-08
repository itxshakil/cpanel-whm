<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use Illuminate\Support\Collection;
use Itxshakil\CpanelWhm\Data\AccountUsage;
use Itxshakil\CpanelWhm\Data\BandwidthUsage;
use Itxshakil\CpanelWhm\Data\DiskUsage;
use Itxshakil\CpanelWhm\Data\Value;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Support\UsernameRules;

/**
 * Disk and bandwidth use per account: get_disk_usage and showbw.
 *
 *     Whm::usage()->account('acme')->disk?->percentUsed();
 *     Whm::usage()->disk()->filter(fn ($usage) => $usage->percentUsed() >= 90);
 */
class Usage extends Module
{
    /**
     * Disk use for every account. WHM serves this from its quota cache unless
     * $fresh is true, which is slower on big servers.
     *
     * @return Collection<int, DiskUsage>
     *
     * @throws WhmException
     */
    public function disk(bool $fresh = false): Collection
    {
        $response = $this->client->call('get_disk_usage', ['cache_mode' => $fresh ? 'off' : 'on']);

        return collect($this->rows($response->get('accounts')))->map(DiskUsage::fromArray(...))->values();
    }

    /**
     * @throws WhmException
     */
    public function diskFor(string $user, bool $fresh = false): ?DiskUsage
    {
        $user = UsernameRules::normalise($user);

        return $this->disk($fresh)->first(static fn (DiskUsage $usage): bool => $usage->user === $user);
    }

    /**
     * Bandwidth for every account in a month (default: this month), optionally
     * only a reseller's accounts.
     *
     * @return Collection<int, BandwidthUsage>
     *
     * @throws WhmException
     */
    public function bandwidth(?int $month = null, ?int $year = null, ?string $reseller = null): Collection
    {
        return $this->showbw(['month' => $month, 'year' => $year, 'showres' => $reseller]);
    }

    /**
     * @throws WhmException
     */
    public function bandwidthFor(string $user, ?int $month = null, ?int $year = null): ?BandwidthUsage
    {
        $user = UsernameRules::normalise($user);

        return $this->showbw([
            'month' => $month,
            'year' => $year,
            'searchtype' => 'user',
            'search' => '^'.preg_quote($user, null).'$',
        ])->first(static fn (BandwidthUsage $usage): bool => $usage->user === $user);
    }

    /**
     * This month's disk and bandwidth for every account, highest share of a
     * limit first. Accounts without limits come last. Two calls to WHM.
     *
     * @return Collection<int, AccountUsage>
     *
     * @throws WhmException
     */
    public function all(bool $fresh = false): Collection
    {
        $disk = $this->disk($fresh)->keyBy(static fn (DiskUsage $usage): string => $usage->user);
        $bandwidth = $this->bandwidth()->keyBy(static fn (BandwidthUsage $usage): string => $usage->user);

        return $disk->keys()->merge($bandwidth->keys())->unique()
            ->map(static fn (string $user): AccountUsage => new AccountUsage($user, $disk->get($user), $bandwidth->get($user)))
            ->sort(static fn (AccountUsage $a, AccountUsage $b): int => [$b->highestPercent() ?? -1, $a->user] <=> [$a->highestPercent() ?? -1, $b->user])
            ->values();
    }

    /**
     * Accounts whose disk or bandwidth use is at or above $percent of the limit.
     *
     * @return Collection<int, AccountUsage>
     *
     * @throws WhmException
     */
    public function nearLimit(float $percent = 90.0, bool $fresh = false): Collection
    {
        return $this->all($fresh)->filter(static fn (AccountUsage $usage): bool => $usage->isNearLimit($percent))->values();
    }

    /**
     * This month's disk and bandwidth for one account.
     *
     * @throws WhmException
     */
    public function account(string $user): AccountUsage
    {
        $user = UsernameRules::normalise($user);

        return new AccountUsage($user, $this->diskFor($user), $this->bandwidthFor($user));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return Collection<int, BandwidthUsage>
     *
     * @throws WhmException
     */
    private function showbw(array $params): Collection
    {
        $response = $this->client->call('showbw', $params);
        $month = Value::int($response->get('month')) ?? (int) date('n');
        $year = Value::int($response->get('year')) ?? (int) date('Y');

        return collect($this->rows($response->get('acct')))
            ->map(static fn (array $row): BandwidthUsage => BandwidthUsage::fromArray($row, $month, $year))
            ->values();
    }
}
