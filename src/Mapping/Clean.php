<?php
declare(strict_types=1);

namespace AtomTool\Mapping;

/**
 * Generic string/number cleaning primitives.
 *
 * IMPORTANT: general-purpose utility library only. It must never contain
 * archive-, standard-, or customer-specific logic. Every method operates
 * purely on strings or numbers and has no knowledge of what the data *means*.
 *
 * Customer-specific decisions live in each customer's mapping.php, where these
 * primitives are composed into per-field cleaners. If a transform would only
 * ever make sense for a single customer, it belongs in that customer's file as
 * an inline closure — not here.
 */
final class Clean
{
    private function __construct()
    {
    }

    /** Remove the given characters from the end of the string. */
    public static function trimTrailing(string $value, string $chars = " \t\n\r\0\x0B"): string
    {
        return rtrim($value, $chars);
    }

    /** Remove the given characters from the start of the string. */
    public static function trimLeading(string $value, string $chars = " \t\n\r\0\x0B"): string
    {
        return ltrim($value, $chars);
    }

    /** Remove the given characters from both ends of the string. */
    public static function trimBoth(string $value, string $chars = " \t\n\r\0\x0B"): string
    {
        return trim($value, $chars);
    }

    /** Collapse all runs of whitespace to a single space, and trim the ends. */
    public static function collapseWhitespace(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /** Strip HTML/XML tags from the string. */
    public static function stripTags(string $value): string
    {
        return strip_tags($value);
    }

    /** Literal search/replace (all occurrences). */
    public static function replace(string $value, string $search, string $replace): string
    {
        if ($search === '') {
            return $value;
        }
        return str_replace($search, $replace, $value);
    }

    /**
     * Truncate to at most $max characters (multibyte-safe). Optionally append
     * a suffix (e.g. "…") when truncation actually occurs.
     */
    public static function truncate(string $value, int $max, string $suffix = ''): string
    {
        if ($max < 0 || mb_strlen($value) <= $max) {
            return $value;
        }
        $keep = $max - mb_strlen($suffix);
        if ($keep < 0) {
            $keep = 0;
        }
        return mb_substr($value, 0, $keep) . $suffix;
    }

    /** Uppercase (multibyte-safe). */
    public static function upper(string $value): string
    {
        return mb_strtoupper($value);
    }

    /** Lowercase (multibyte-safe). */
    public static function lower(string $value): string
    {
        return mb_strtolower($value);
    }

    /** Title case (multibyte-safe). */
    public static function titleCase(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE);
    }

    /** Return $fallback when the (trimmed) value is empty; otherwise the value. */
    public static function defaultIfEmpty(string $value, string $fallback): string
    {
        return trim($value) === '' ? $fallback : $value;
    }

    /** Parse to int (0 on non-numeric). Number primitive. */
    public static function toInt(string $value): int
    {
        return (int) preg_replace('/[^\d\-]/', '', $value);
    }

    /** Parse to float (0.0 on non-numeric). Number primitive. */
    public static function toFloat(string $value): float
    {
        $n = preg_replace('/[^\d.\-]/', '', $value);
        return $n === '' ? 0.0 : (float) $n;
    }

    /** Format a number with fixed decimals and separators. Number primitive. */
    public static function formatNumber(
        float $value,
        int $decimals = 0,
        string $decimalSep = '.',
        string $thousandsSep = ','
    ): string {
        return number_format($value, $decimals, $decimalSep, $thousandsSep);
    }
}