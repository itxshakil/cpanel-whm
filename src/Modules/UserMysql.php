<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Itxshakil\CpanelWhm\Data\CreatedMysqlUser;
use Itxshakil\CpanelWhm\Data\MysqlDatabase;
use Itxshakil\CpanelWhm\Data\MysqlUser;
use Itxshakil\CpanelWhm\Data\UapiResult;
use Itxshakil\CpanelWhm\Exceptions\UapiCallFailed;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Support\Passwords;
use SensitiveParameter;

/**
 * MySQL databases, users and grants of one account, through UAPI Mysql:
 * Whm::asUser('acme')->mysql()->createDatabase('acme_shop').
 *
 * Names are sent as given. When the server prefixes database names (the
 * default), they must start with the account's username and an underscore.
 */
class UserMysql extends UserModule
{
    /**
     * Pass $page (from 1) to read $perPage at a time.
     *
     * @return Collection<int, MysqlDatabase>
     *
     * @throws InvalidArgumentException when $page or $perPage is below 1
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function databases(?int $page = null, int $perPage = 100): Collection
    {
        return collect(self::rows($this->user->api()->mysql()->listDatabases(extra: self::page($page, $perPage))->data))
            ->map(MysqlDatabase::fromArray(...))
            ->values();
    }

    /**
     * Pass $page (from 1) to read $perPage at a time.
     *
     * @return Collection<int, MysqlUser>
     *
     * @throws InvalidArgumentException when $page or $perPage is below 1
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function users(?int $page = null, int $perPage = 100): Collection
    {
        return collect(self::rows($this->user->api()->mysql()->listUsers(extra: self::page($page, $perPage))->data))
            ->map(MysqlUser::fromArray(...))
            ->values();
    }

    /**
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function createDatabase(string $name): UapiResult
    {
        return $this->user->api()->mysql()->createDatabase(name: $name);
    }

    /**
     * Drop a database and its data. This cannot be undone.
     *
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function deleteDatabase(string $name): UapiResult
    {
        return $this->user->api()->mysql()->deleteDatabase(name: $name);
    }

    /**
     * Create a database user. Without a password a strong one is generated;
     * CreatedMysqlUser::$password holds the one it was created with.
     *
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function createUser(string $name, #[SensitiveParameter] ?string $password = null): CreatedMysqlUser
    {
        $password ??= Passwords::generate();

        return new CreatedMysqlUser($name, $password, $this->user->api()->mysql()->createUser(name: $name, password: $password));
    }

    /**
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function changePassword(string $user, #[SensitiveParameter] string $password): UapiResult
    {
        return $this->user->api()->mysql()->setPassword(password: $password, user: $user);
    }

    /**
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function deleteUser(string $name): UapiResult
    {
        return $this->user->api()->mysql()->deleteUser(name: $name);
    }

    /**
     * Give a user privileges on a database: all of them by default, or a
     * list such as ['SELECT', 'INSERT', 'UPDATE', 'DELETE'].
     *
     * @param  list<string>  $privileges  at least one; use revoke() to take them away
     *
     * @throws InvalidArgumentException when $privileges is empty
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function grant(string $user, string $database, array $privileges = ['ALL PRIVILEGES']): UapiResult
    {
        $privileges = array_values(array_filter(
            array_map(static fn (string $privilege): string => mb_strtoupper(trim($privilege)), $privileges),
            static fn (string $privilege): bool => $privilege !== '',
        ));

        // An empty list would send privileges='', an error or a silent no-op
        // depending on the server, and never what the caller meant.
        if ($privileges === []) {
            throw new InvalidArgumentException("Name at least one privilege to grant {$user} on {$database}, or use revoke() to remove them.");
        }

        return $this->user->api()->mysql()->setPrivilegesOnDatabase(
            database: $database,
            user: $user,
            privileges: implode(',', $privileges),
        );
    }

    /**
     * Remove every privilege a user has on a database.
     *
     * @throws UapiCallFailed
     * @throws WhmException
     */
    public function revoke(string $user, string $database): UapiResult
    {
        return $this->user->api()->mysql()->revokeAccessToDatabase(database: $database, user: $user);
    }
}
