<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use Illuminate\Support\Collection;
use Itxshakil\CpanelWhm\Data\ResellerAccountCounts;
use Itxshakil\CpanelWhm\Data\ResellerStats;
use Itxshakil\CpanelWhm\Data\Value;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\Support\UsernameRules;
use Itxshakil\CpanelWhm\WhmResponse;

/**
 * Resellers and their privileges: listresellers, setupreseller,
 * unsetupreseller, resellerstats, acctcounts, setacls, listacls, saveacllist,
 * setresellerlimits, suspendreseller, unsuspendreseller, terminatereseller.
 *
 * Privileges are WHM's ACL names without the "acl-" prefix: "create-acct",
 * "suspend-acct", "list-accts", ...
 */
class Resellers extends Module
{
    /**
     * @return Collection<int, string>
     *
     * @throws WhmException
     */
    public function list(): Collection
    {
        return collect(Value::strings($this->client->call('listresellers')->get('reseller')))->values();
    }

    /**
     * Give an existing cPanel account reseller privileges.
     *
     * @param  bool  $ownAccount  make the account own itself
     *
     * @throws WhmException
     */
    public function create(string $user, bool $ownAccount = false): WhmResponse
    {
        return $this->client->call('setupreseller', ['user' => UsernameRules::normalise($user), 'makeowner' => $ownAccount], HttpMethod::Post);
    }

    /**
     * Take reseller privileges away. The account and the accounts it owns stay.
     *
     * @throws WhmException
     */
    public function remove(string $user): WhmResponse
    {
        return $this->client->call('unsetupreseller', ['user' => UsernameRules::normalise($user)], HttpMethod::Post);
    }

    /**
     * @throws WhmException
     */
    public function stats(string $user, ?int $month = null, ?int $year = null): ResellerStats
    {
        $data = $this->client->call('resellerstats', ['user' => UsernameRules::normalise($user), 'month' => $month, 'year' => $year])->get('reseller');

        return ResellerStats::fromArray(is_array($data) ? $data : []);
    }

    /**
     * @throws WhmException
     */
    public function accountCounts(?string $user = null): ResellerAccountCounts
    {
        $data = $this->client->call('acctcounts', ['user' => $user === null ? null : UsernameRules::normalise($user)])->get('reseller');

        return ResellerAccountCounts::fromArray(is_array($data) ? $data : []);
    }

    /**
     * Assign a saved privilege list (see saveAcl()) to a reseller.
     *
     * @throws WhmException
     */
    public function assignAcl(string $reseller, string $aclList): WhmResponse
    {
        return $this->client->call('setacls', ['reseller' => UsernameRules::normalise($reseller), 'acllist' => $aclList], HttpMethod::Post);
    }

    /**
     * Set a reseller's privileges directly. Privileges not listed are removed.
     *
     * @param  list<string>  $privileges  e.g. ['create-acct', 'suspend-acct', 'list-accts']
     *
     * @throws WhmException
     */
    public function setPrivileges(string $reseller, array $privileges): WhmResponse
    {
        return $this->client->call('setacls', ['reseller' => UsernameRules::normalise($reseller), ...$this->aclParams($privileges)], HttpMethod::Post);
    }

    /**
     * Saved privilege lists, name => enabled privileges.
     *
     * @return Collection<string, list<string>>
     *
     * @throws WhmException
     */
    public function acls(): Collection
    {
        return collect($this->rows($this->client->call('listacls')->get('acl')))
            ->mapWithKeys(static function (array $row): array {
                $privileges = [];

                foreach (is_array($row['privileges'] ?? null) ? $row['privileges'] : [] as $name => $enabled) {
                    if (Value::bool($enabled)) {
                        $privileges[] = (string) $name;
                    }
                }

                return [(string) Value::string($row['name'] ?? null) => $privileges];
            })
            ->except(['']);
    }

    /**
     * Create or replace a saved privilege list.
     *
     * @param  list<string>  $privileges
     *
     * @throws WhmException
     */
    public function saveAcl(string $name, array $privileges): WhmResponse
    {
        return $this->client->call('saveacllist', ['acllist' => $name, ...$this->aclParams($privileges)], HttpMethod::Post);
    }

    /**
     * Limit a reseller's accounts, disk (MB) and bandwidth (MB). Leave an
     * argument null to keep its current setting.
     *
     * @throws WhmException
     */
    public function setLimits(
        string $user,
        ?int $accounts = null,
        ?int $diskMb = null,
        ?int $bandwidthMb = null,
        ?bool $overselling = null,
        ?bool $oversellDisk = null,
        ?bool $oversellBandwidth = null,
    ): WhmResponse {
        $limitResources = $diskMb !== null || $bandwidthMb !== null ? true : null;

        return $this->client->call('setresellerlimits', [
            'user' => UsernameRules::normalise($user),
            'enable_account_limit' => $accounts !== null ? true : null,
            'account_limit' => $accounts,
            'enable_resource_limits' => $limitResources,
            'diskspace_limit' => $diskMb,
            'bandwidth_limit' => $bandwidthMb,
            'enable_overselling' => $overselling,
            'enable_overselling_diskspace' => $oversellDisk,
            'enable_overselling_bandwidth' => $oversellBandwidth,
        ], HttpMethod::Post);
    }

    /**
     * Suspend a reseller and, unless $resellerOnly, every account it owns.
     *
     * @param  bool  $lock  only root can unsuspend
     *
     * @throws WhmException
     */
    public function suspend(string $user, string $reason = '', bool $lock = false, bool $resellerOnly = false): WhmResponse
    {
        return $this->client->call('suspendreseller', [
            'user' => UsernameRules::normalise($user),
            'reason' => $reason,
            'disallow' => $lock,
            'reseller-only' => $resellerOnly,
        ], HttpMethod::Post);
    }

    /**
     * @throws WhmException
     */
    public function unsuspend(string $user, bool $resellerOnly = false): WhmResponse
    {
        return $this->client->call('unsuspendreseller', ['user' => UsernameRules::normalise($user), 'reseller-only' => $resellerOnly], HttpMethod::Post);
    }

    /**
     * Delete a reseller's accounts and, if $includeOwnAccount, the reseller's
     * own account. This cannot be undone.
     *
     * @throws WhmException
     */
    public function terminate(string $user, bool $includeOwnAccount = true): WhmResponse
    {
        return $this->client->call('terminatereseller', [
            'user' => UsernameRules::normalise($user),
            'terminatereseller' => $includeOwnAccount,
        ], HttpMethod::Post, 600);
    }

    /**
     * @param  list<string>  $privileges
     * @return array<string, int>
     */
    private function aclParams(array $privileges): array
    {
        $params = [];

        foreach ($privileges as $privilege) {
            $params['acl-'.preg_replace('/^acl-/', '', trim($privilege))] = 1;
        }

        return $params;
    }
}
