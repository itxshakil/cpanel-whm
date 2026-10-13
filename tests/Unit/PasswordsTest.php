<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Unit;

use InvalidArgumentException;
use Itxshakil\CpanelWhm\Support\Passwords;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PasswordsTest extends TestCase
{
    #[Test]
    public function a_password_has_the_length_asked_for_and_every_character_class(): void
    {
        foreach ([Passwords::MIN_LENGTH, 24, 64] as $length) {
            $password = Passwords::generate($length);

            self::assertSame($length, strlen($password));
            self::assertMatchesRegularExpression('/[a-z]/', $password);
            self::assertMatchesRegularExpression('/[A-Z]/', $password);
            self::assertMatchesRegularExpression('/\d/', $password);
            self::assertMatchesRegularExpression('/[^a-zA-Z\d]/', $password);
        }
    }

    #[Test]
    #[DataProvider('tooShort')]
    public function a_length_below_the_minimum_is_refused_instead_of_quietly_raised(int $length): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least '.Passwords::MIN_LENGTH);

        Passwords::generate($length);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function tooShort(): iterable
    {
        yield 'negative' => [-5];
        yield 'zero' => [0];
        yield 'two' => [2];
        yield 'one below the minimum' => [Passwords::MIN_LENGTH - 1];
    }
}
