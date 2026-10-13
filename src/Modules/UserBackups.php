<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use Illuminate\Support\Collection;
use Itxshakil\CpanelWhm\Data\Value;
use Itxshakil\CpanelWhm\Exceptions\UapiCallFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmException;

/**
 * The account's own backups, through UAPI Backup:
 * Whm::asUser('acme')->backups()->toHomeDirectory().
 *
 * Server-wide backups and restores are under Whm::backups(). Copies to a
 * remote host (fullbackup_to_scp_with_key, _ftp, ...) are under
 * Whm::asUser('acme')->api()->backup().
 */
class UserBackups extends UserModule
{
    /**
     * The dates of the server's backups of this account, oldest first, as
     * YYYY-MM-DD.
     *
     * @return Collection<int, string>
     *
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function list(): Collection
    {
        $dates = [];

        foreach (Value::strings($this->user->api()->backup()->listBackups()->data) as $date) {
            $dates[substr($date, 0, 10)] = true;
        }

        unset($dates['']);
        ksort($dates);

        /** @var list<string> $sorted */
        $sorted = array_map(strval(...), array_keys($dates));

        return collect($sorted);
    }

    /**
     * Start a full backup into the home directory, as
     * backup-MM.DD.YYYY_HH-mm-ss_user.tar.gz. It runs in the background:
     * returns its process id, and $email gets a message when it finishes.
     *
     * @param  bool  $includeHomeDirectory  false leaves the home directory's files out
     *
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function toHomeDirectory(?string $email = null, bool $includeHomeDirectory = true): ?string
    {
        $result = $this->user->api()->backup()->fullbackupToHomedir(
            email: $email,
            homedir: $includeHomeDirectory ? null : 'skip',
        );

        return Value::string(is_array($result->data) ? ($result->data['pid'] ?? null) : null);
    }
}
