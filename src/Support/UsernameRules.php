<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Support;

use Itxshakil\CpanelWhm\Exceptions\InvalidUsername;

/**
 * cPanel's username rules, checked locally so a bad name fails fast with a
 * clear reason instead of a round trip to WHM.
 *
 * WHM still has the final say (for example, the first eight characters must
 * be unique on the server); Accounts::isUsernameAvailable() asks it.
 */
final class UsernameRules
{
    public const int MAX_LENGTH = 16;

    /**
     * Names WHM refuses: cPanel's default reserved aliases (docs.cpanel.net,
     * "Reserved, Invalid, and Misconfigured Usernames") plus service accounts
     * cPanel creates. Aliases with a hyphen, such as mailer-daemon, are left
     * out: the character rule already refuses them. A server's own /etc/aliases
     * can reserve more, which only WHM knows about.
     *
     * @var list<string>
     */
    public const array RESERVED = [
        'abrt', 'abuse', 'adm', 'admin', 'amandabackup', 'apache', 'bin', 'canna', 'cpanel', 'daemon', 'dbus',
        'decode', 'desktop', 'dovecot', 'dumper', 'fax', 'ftp', 'ftpadm', 'ftpadmin', 'games', 'gdm',
        'gopher', 'haldaemon', 'halt', 'hkadmincp', 'hostmaster', 'ident', 'info', 'ingres', 'ldap', 'lp',
        'mail', 'mailman', 'mailnull', 'manager', 'marketing', 'mysql', 'named', 'netdump', 'news', 'newsadm',
        'newsadmin', 'nfsnobody', 'nobody', 'noc', 'nscd', 'ntp', 'nut', 'operator', 'pcap', 'postfix',
        'postgres', 'postmaster', 'privoxy', 'pvm', 'quagga', 'radiusd', 'radvd', 'root', 'rpc', 'rpcuser',
        'rpm', 'sales', 'saslauth', 'security', 'shutdown', 'smmsp', 'squid', 'sshd', 'sudo', 'support',
        'sync', 'system', 'tcpdump', 'toor', 'usenet', 'user', 'uucp', 'vcsa', 'virtfs', 'webalizer',
        'webmaster', 'wheel', 'whm', 'wnn', 'www', 'xfs',
    ];

    /**
     * WHM lowercases usernames itself; doing it first means the name you store
     * is the name WHM uses.
     */
    public static function normalise(string $username): string
    {
        return mb_strtolower(trim($username));
    }

    /**
     * The first rule the username breaks, or null when it passes.
     */
    public static function violation(string $username): ?string
    {
        $username = self::normalise($username);

        return match (true) {
            $username === '' => 'the username is empty.',
            mb_strlen($username) > self::MAX_LENGTH => 'use at most '.self::MAX_LENGTH.' characters.',
            preg_match('/^[0-9]/', $username) === 1 => 'it cannot start with a number.',
            preg_match('/^[a-z0-9]+$/', $username) !== 1 => 'use only lowercase letters and numbers.',
            str_starts_with($username, 'test') => 'it cannot start with "test".',
            str_ends_with($username, 'assword') => 'it cannot end in "assword".',
            in_array($username, self::RESERVED, true) => 'it is reserved by the system.',
            default => null,
        };
    }

    public static function passes(string $username): bool
    {
        return self::violation($username) === null;
    }

    /**
     * @throws InvalidUsername
     */
    public static function assertValid(string $username): string
    {
        $violation = self::violation($username);

        if ($violation !== null) {
            throw new InvalidUsername($username, $violation);
        }

        return self::normalise($username);
    }
}
