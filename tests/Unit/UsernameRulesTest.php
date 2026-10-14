<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Unit;

use Itxshakil\CpanelWhm\Exceptions\InvalidUsername;
use Itxshakil\CpanelWhm\Support\UsernameRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UsernameRulesTest extends TestCase
{
    #[Test]
    public function valid_usernames_pass_and_are_lowercased(): void
    {
        self::assertSame('acme2026', UsernameRules::assertValid(' Acme2026 '));
        self::assertTrue(UsernameRules::passes('a'));
    }

    #[Test]
    #[DataProvider('brokenRules')]
    public function it_names_the_rule_a_username_breaks(string $username, string $rule): void
    {
        self::assertStringContainsString($rule, (string) UsernameRules::violation($username));
    }

    /** @return array<string, array{string, string}> */
    public static function brokenRules(): array
    {
        return [
            'empty' => ['', 'empty'],
            'too long' => ['abcdefghijklmnopq', '16 characters'],
            'leading digit' => ['1acme', 'start with a number'],
            'hyphen' => ['acme-co', 'lowercase letters and numbers'],
            'underscore' => ['acme_co', 'lowercase letters and numbers'],
            'starts with test' => ['testing1', 'start with "test"'],
            'reserved' => ['root', 'reserved'],
            'ends in assword' => ['mypassword', 'end in "assword"'],
            'reserved service account' => ['dovecot', 'reserved'],
            'reserved role alias' => ['support', 'reserved'],
        ];
    }

    /**
     * cPanel's default reserved aliases, from "Reserved, Invalid, and
     * Misconfigured Usernames" (docs.cpanel.net, July 2026).
     */
    #[Test]
    public function cpanels_documented_reserved_names_are_refused(): void
    {
        $documented = [
            'admin', 'abuse', 'adm', 'amandabackup', 'apache', 'bin', 'canna', 'daemon', 'dbus', 'decode', 'desktop',
            'dovecot', 'dumper', 'fax', 'ftp', 'games', 'gdm', 'gopher', 'halt', 'hostmaster', 'hkadmincp', 'ident',
            'ingres', 'ldap', 'lp', 'mail', 'mailnull', 'manager', 'mysql', 'named', 'netdump', 'news', 'nfsnobody',
            'nobody', 'noc', 'nscd', 'ntp', 'nut', 'operator', 'pcap', 'postfix', 'postgres', 'postmaster', 'privoxy',
            'pvm', 'quagga', 'radiusd', 'radvd', 'root', 'rpc', 'rpcuser', 'rpm', 'security', 'shutdown', 'smmsp',
            'squid', 'sshd', 'sudo', 'sync', 'system', 'toor', 'uucp', 'vcsa', 'webalizer', 'webmaster', 'wheel',
            'wnn', 'xfs', 'info', 'marketing', 'sales', 'support', 'newsadm', 'newsadmin', 'usenet', 'ftpadm',
            'ftpadmin', 'www',
        ];

        foreach ($documented as $name) {
            self::assertSame('it is reserved by the system.', UsernameRules::violation($name), $name);
        }
    }

    #[Test]
    public function names_that_only_contain_a_rule_word_still_pass(): void
    {
        // "assword" only matters at the end, and reserved names only as the whole name.
        self::assertTrue(UsernameRules::passes('passwordco'));
        self::assertTrue(UsernameRules::passes('rootbeer'));
        self::assertTrue(UsernameRules::passes('mysupport'));
    }

    #[Test]
    public function assert_valid_throws_with_the_rule(): void
    {
        try {
            UsernameRules::assertValid('test1');
            self::fail('Expected InvalidUsername');
        } catch (InvalidUsername $invalidUsername) {
            self::assertSame('test1', $invalidUsername->username());
            self::assertSame('it cannot start with "test".', $invalidUsername->rule());
        }
    }
}
