<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Generator;

/**
 * Turns cPanel names (createacct, api_token_create, acl-add-pkg, MAX_EMAIL_PER_HOUR,
 * "Login Security (cPHulk)") into PHP identifiers.
 */
final class Naming
{
    public static function camel(string $name): string
    {
        $name = (string) preg_replace('/(^|[^A-Za-z])cP/', '$1Cp', $name);   // cPanel, cPHulk, cPGreyList
        preg_match_all('/[A-Z]+(?=[A-Z][a-z])|[A-Z]?[a-z]+|[A-Z]+|\d+/', $name, $matches);

        $out = '';

        foreach ($matches[0] as $index => $word) {
            if (strtoupper($word) === $word) {
                $word = strtolower($word);
            }

            $out .= $index === 0 ? lcfirst($word) : ucfirst($word);
        }

        if ($out === '') {
            return '';
        }

        return ctype_digit($out[0]) ? 'v'.$out : $out;
    }

    public static function studly(string $name): string
    {
        return ucfirst(self::camel($name));
    }

    /**
     * Whether a parameter name can become a PHP variable at all. Wildcards
     * ("copymysqldb-*") and prose ("Variable Names and Values") cannot.
     */
    public static function isUsableParameter(string $name): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_.\-]*$/', $name) === 1;
    }
}
