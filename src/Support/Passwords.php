<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Support;

use InvalidArgumentException;

/**
 * Strong passwords for accounts created without one.
 */
final class Passwords
{
    private const array CLASSES = [
        'abcdefghijkmnopqrstuvwxyz',
        'ABCDEFGHJKLMNPQRSTUVWXYZ',
        '23456789',
        '!@#%^*-_=+',
    ];

    /**
     * Shorter than this is too weak to hand out, and WHM's password strength
     * check would likely refuse it anyway.
     */
    public const int MIN_LENGTH = 12;

    /**
     * $length characters with at least one of each class: lowercase,
     * uppercase, digit and symbol. Easily confused characters (l, I, O, 0, 1)
     * are left out, since people may have to read the password back.
     *
     * @throws InvalidArgumentException when $length is below MIN_LENGTH
     */
    public static function generate(int $length = 24): string
    {
        // A misconfigured length used to come back as a 4-character password without a word.
        if ($length < self::MIN_LENGTH) {
            throw new InvalidArgumentException('A generated password needs at least '.self::MIN_LENGTH." characters, {$length} were asked for.");
        }

        $all = implode('', self::CLASSES);
        $characters = array_map(self::pick(...), self::CLASSES);

        while (count($characters) < $length) {
            $characters[] = self::pick($all);
        }

        for ($i = count($characters) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
        }

        return implode('', $characters);
    }

    private static function pick(string $alphabet): string
    {
        return $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
}
