<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Support;

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
     * $length characters with at least one of each class: lowercase,
     * uppercase, digit and symbol. Easily confused characters (l, I, O, 0, 1)
     * are left out, since people may have to read the password back.
     */
    public static function generate(int $length = 24): string
    {
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
