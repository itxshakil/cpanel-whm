<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Generator;

/**
 * Markdown from the spec, made safe and short enough for a PHPDoc block.
 */
final class Text
{
    public static function clean(string $markdown): string
    {
        $text = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $markdown) ?? $markdown;   // [label](url) -> label
        $text = preg_replace('/<[^>]+>/', '', $text) ?? $text;                          // stray HTML
        $text = str_replace(['**', '*/', '™', '®'], ['', '* /', '', ''], $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * The first paragraph, cleaned, cut at a sentence boundary near $limit characters.
     */
    public static function firstParagraph(string $markdown, int $limit = 320): string
    {
        $paragraph = preg_split('/\n\s*\n/', trim($markdown))[0] ?? '';
        $text = self::clean($paragraph);

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit);
        $stop = mb_strrpos($cut, '. ');

        return $stop !== false && $stop > $limit / 3 ? mb_substr($cut, 0, $stop + 1) : rtrim($cut).'…';
    }

    public static function firstSentence(string $markdown, int $limit = 160): string
    {
        $text = self::clean(preg_split('/\n\s*\n|\n\*/', trim($markdown))[0] ?? '');

        if (preg_match('/^(.+?\.)(\s|$)/u', $text, $match) === 1) {
            $text = $match[1];
        }

        return mb_strlen($text) <= $limit ? $text : rtrim(mb_substr($text, 0, $limit - 1)).'…';
    }

    /**
     * @return list<string>
     */
    public static function wrap(string $text, int $width = 100): array
    {
        if ($text === '') {
            return [];
        }

        return explode("\n", wordwrap($text, $width, "\n", false));
    }

    public static function quote(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }
}
