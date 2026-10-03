<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Support;

/**
 * Which WHM API 1 functions have a typed method in this package. Everything
 * else is one Whm::call('function', [...]) away.
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
