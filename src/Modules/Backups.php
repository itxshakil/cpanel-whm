<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use DateTimeInterface;
use Illuminate\Support\Collection;
use Itxshakil\CpanelWhm\Data\RestoreQueue;
use Itxshakil\CpanelWhm\Data\Value;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Support\UsernameRules;

/**
 * Server backups and account restores: backup_config_get, backup_date_list,
 * backup_user_list, restore_queue_*, start_background_pkgacct.
 *
 * For an account's own backups (full backup to the home directory or FTP,
 * restoring files or databases), use Whm::asUser($user)->api()->backup().
 *
 *     $dates = Whm::backups()->dates();                       // ['2026-10-01', ...]
 *     Whm::backups()->restore('acme', $dates->last());
 *     Whm::backups()->queue()->stateOf('acme');               // pending, active, completed
 */
class Backups extends Module
{
    /**
     * The server's backup configuration as WHM stores it.
     *
     * @return array<array-key, mixed>
     *
     * @throws WhmException
     */
    public function config(): array
    {
        $config = $this->client->call('backup_config_get')->get('backup_config');

        return is_array($config) ? $config : [];
    }

    /**
     * Dates (YYYY-MM-DD, oldest first) that have a backup on the server.
     *
     * @return Collection<int, string>
     *
     * @throws WhmException
     */
    public function dates(): Collection
    {
        return collect(Value::strings($this->client->call('backup_date_list')->get('backup_set')))
            ->map(static fn (string $date): string => substr($date, 0, 10))
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * Accounts in the backup from a date, keyed by username, with WHM's status:
     * active, inactive or no_backup.
     *
     * @return Collection<string, string>
     *
     * @throws WhmException
     */
    public function users(DateTimeInterface|string $date): Collection
    {
        $response = $this->client->call('backup_user_list', ['restore_point' => $this->date($date)]);

        return collect($this->rows($response->get('user')))
            ->mapWithKeys(static fn (array $row): array => [(string) Value::string($row['username'] ?? null) => Value::string($row['status'] ?? null) ?? 'active'])
            ->except(['']);
    }

    /**
     * Queue an account restore from a backup date and, unless $start is false,
     * start the queue. Returns WHM's queue id. Track it with queue().
     *
     * @throws WhmException
     */
    public function restore(
        string $user,
        DateTimeInterface|string $date,
        bool $databases = true,
        bool $mail = true,
        bool $subdomains = true,
        bool $dedicatedIp = false,
        ?string $destination = null,
        bool $start = true,
    ): ?string {
        $response = $this->client->call('restore_queue_add_task', [
            'user' => UsernameRules::normalise($user),
            'restore_point' => $this->date($date),
            'mysql' => $databases,
            'mail_config' => $mail,
            'subdomains' => $subdomains,
            'give_ip' => $dedicatedIp,
            'destid' => $destination,
        ], HttpMethod::Post);

        if ($start) {
            $this->client->call('restore_queue_activate', [], HttpMethod::Post);
        }

        return Value::string($response->get('queue_id'));
    }

    /**
     * @throws WhmException
     */
    public function queue(): RestoreQueue
    {
        return RestoreQueue::fromArray($this->client->call('restore_queue_state')->data);
    }

    /**
     * Package an account into a cpmove archive in the background (pkgacct).
     * Returns the session id; poll it with backupStatus().
     *
     * @param  array<string, mixed>  $options  any other start_background_pkgacct parameter, e.g. ['skiplogs' => true]
     *
     * @throws WhmException
     */
    public function backupAccount(string $user, bool $compress = true, bool $skipHomeDir = false, ?string $directory = null, array $options = []): ?string
    {
        $response = $this->client->call('start_background_pkgacct', [
            'user' => UsernameRules::normalise($user),
            'compressionsetting' => $compress ? 'compress' : null,
            'skiphomedir' => $skipHomeDir,
            'tarroot' => $directory,
            ...$options,
        ], HttpMethod::Post);

        return Value::string($response->get('session_id'));
    }

    /**
     * RUNNING, COMPLETED or FAILED.
     *
     * @throws WhmException
     */
    public function backupStatus(string $sessionId): ?string
    {
        return Value::string($this->client->call('get_pkgacct_session_state', ['session_id' => $sessionId])->get('state'));
    }

    private function date(DateTimeInterface|string $date): string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : substr(trim($date), 0, 10);
    }
}
