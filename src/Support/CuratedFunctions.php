<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Support;

/**
 * Which WHM API 1 functions have a hand-written method with typed results.
 * Every other documented function has a generated method under Whm::api()
 * (see FunctionCatalog), and anything at all is one Whm::call() away.
 */
final class CuratedFunctions
{
    /**
     * @var array<string, string> function => method
     */
    public const array MAP = [
        'createacct' => 'accounts()->create()',
        'listaccts' => 'accounts()->list() / accounts()->search()',
        'accountsummary' => 'accounts()->find() / accounts()->exists()',
        'verify_new_username' => 'accounts()->isUsernameAvailable()',
        'removeacct' => 'accounts()->remove()',
        'passwd' => 'accounts()->changePassword()',
        'changepackage' => 'accounts()->changePackage()',
        'modifyacct' => 'accounts()->modify()',
        'suspendacct' => 'suspensions()->suspend()',
        'unsuspendacct' => 'suspensions()->unsuspend()',
        'listsuspended' => 'suspensions()->list()',
        'listpkgs' => 'packages()->list() / packages()->find()',
        'editquota' => 'quotas()->setDiskQuota()',
        'limitbw' => 'quotas()->setBandwidthLimit()',
        'create_user_session' => 'sessions()->create()',
        'uapi_cpanel' => 'asUser($user)->uapi()',
        'version' => 'server()->version()',
        'applist' => 'server()->functions()',
        'myprivs' => 'server()->privileges() / server()->hasPrivilege()',
        'gethostname' => 'server()->hostname()',
        'systemloadavg' => 'server()->loadAverage()',
        'servicestatus' => 'server()->services()',
        'restartservice' => 'server()->restartService()',
        'getdiskusage' => 'server()->partitions()',
        'getpkginfo' => 'packages()->info()',
        'addpkg' => 'packages()->create()',
        'editpkg' => 'packages()->update()',
        'killpkg' => 'packages()->delete()',
        'listzones' => 'dns()->zones()',
        'parse_dns_zone' => 'dns()->zone()',
        'mass_edit_dns_zone' => 'dns()->add() / update() / remove() / edit()',
        'adddns' => 'dns()->create()',
        'killdns' => 'dns()->delete()',
        'resetzone' => 'dns()->reset()',
        'get_domain_info' => 'domains()->list() / domains()->find()',
        'getdomainowner' => 'domains()->owner()',
        'domainuserdata' => 'domains()->userData()',
        'create_subdomain' => 'domains()->createSubdomain()',
        'create_parked_domain_for_user' => 'domains()->createAlias()',
        'delete_domain' => 'domains()->delete()',
        'get_disk_usage' => 'usage()->disk() / usage()->diskFor()',
        'showbw' => 'usage()->bandwidth() / usage()->bandwidthFor()',
        'backup_config_get' => 'backups()->config()',
        'backup_date_list' => 'backups()->dates()',
        'backup_user_list' => 'backups()->users()',
        'restore_queue_add_task' => 'backups()->restore()',
        'restore_queue_activate' => 'backups()->restore()',
        'restore_queue_state' => 'backups()->queue()',
        'start_background_pkgacct' => 'backups()->backupAccount()',
        'get_pkgacct_session_state' => 'backups()->backupStatus()',
        'listresellers' => 'resellers()->list()',
        'setupreseller' => 'resellers()->create()',
        'unsetupreseller' => 'resellers()->remove()',
        'resellerstats' => 'resellers()->stats()',
        'acctcounts' => 'resellers()->accountCounts()',
        'setacls' => 'resellers()->assignAcl() / resellers()->setPrivileges()',
        'listacls' => 'resellers()->acls()',
        'saveacllist' => 'resellers()->saveAcl()',
        'setresellerlimits' => 'resellers()->setLimits()',
        'suspendreseller' => 'resellers()->suspend()',
        'unsuspendreseller' => 'resellers()->unsuspend()',
        'terminatereseller' => 'resellers()->terminate()',
        'start_autossl_check_for_one_user' => 'ssl()->runAutoSsl()',
        'get_autossl_problems_for_user' => 'ssl()->autoSslProblems()',
        'installssl' => 'ssl()->install()',
        'api_token_list' => 'tokens()->list() / tokens()->expiringWithin()',
        'api_token_create' => 'tokens()->create()',
        'api_token_revoke' => 'tokens()->revoke()',
    ];

    /**
     * Functions that destroy data. whm:call asks for confirmation before running them.
     *
     * @var list<string>
     */
    public const array DESTRUCTIVE = [
        'removeacct',
        'terminatereseller',
        'killdns',
        'killpkg',
        'removezonerecord',
        'mass_edit_dns_zone',
        'backup_destination_delete',
        'api_token_revoke',
        'delete_domain',
        'resetzone',
        'unsetupreseller',
        'restore_queue_clear_all_tasks',
        'reboot',
    ];

    public static function has(string $function): bool
    {
        return array_key_exists($function, self::MAP);
    }

    public static function isDestructive(string $function): bool
    {
        return in_array($function, self::DESTRUCTIVE, true);
    }
}
