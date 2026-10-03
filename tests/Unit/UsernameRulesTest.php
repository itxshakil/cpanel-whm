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
        ];
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
