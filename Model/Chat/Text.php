<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat;

/**
 * Small text helpers shared by the intent router, Q&A matcher and product finder.
 */
class Text
{
    /**
     * @return string[] lower-cased word tokens (letters/digits, unicode aware)
     */
    public static function tokens(string $text): array
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: [];
        return array_values(array_filter($tokens, function ($token) {
            return $token !== '';
        }));
    }

    public static function normalize(string $text): string
    {
        return implode(' ', self::tokens($text));
    }

    /**
     * Jaccard similarity of the token sets, 0..1.
     */
    public static function similarity(string $a, string $b): float
    {
        $ta = array_unique(self::tokens($a));
        $tb = array_unique(self::tokens($b));
        if ($ta === [] || $tb === []) {
            return 0.0;
        }
        $intersection = count(array_intersect($ta, $tb));
        $union = count(array_unique(array_merge($ta, $tb)));
        return $union > 0 ? $intersection / $union : 0.0;
    }

    public static function looksLikeEmail(string $text): ?string
    {
        if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text, $m)) {
            return mb_strtolower($m[0]);
        }
        return null;
    }

    /**
     * Order numbers on this store are 9-digit increment ids (e.g. 000000034); accept 6+ digits, optional # prefix.
     */
    public static function looksLikeOrderNumber(string $text): ?string
    {
        if (preg_match('/(?<![\d.])#?\s?(\d{6,12})(?![\d.])/', $text, $m)) {
            return $m[1];
        }
        return null;
    }

    public static function looksLikeCode(string $text): ?string
    {
        $trimmed = trim($text);
        if (preg_match('/^\d{6}$/', $trimmed)) {
            return $trimmed;
        }
        if (preg_match('/\bcode\b[^\d]{0,15}(\d{6})\b/i', $trimmed, $m)) {
            return $m[1];
        }
        return null;
    }

    public static function truncate(string $text, int $chars): string
    {
        if (mb_strlen($text) <= $chars) {
            return $text;
        }
        $cut = mb_substr($text, 0, $chars);
        $lastStop = max(mb_strrpos($cut, '. ') ?: 0, mb_strrpos($cut, "\n") ?: 0);
        if ($lastStop > $chars * 0.4) {
            $cut = mb_substr($cut, 0, $lastStop + 1);
        }
        return rtrim($cut) . ' …';
    }
}
