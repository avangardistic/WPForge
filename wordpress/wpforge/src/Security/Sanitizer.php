<?php

namespace WPForge\Security;

/**
 * Input sanitizer for WPForge requests.
 */
class Sanitizer
{
    /**
     * Sanitize a plain text string.
     */
    public static function text(mixed $value): string
    {
        return sanitize_text_field((string) ($value ?? ''));
    }

    /**
     * Sanitize a textarea / multiline text.
     */
    public static function textarea(mixed $value): string
    {
        return sanitize_textarea_field((string) ($value ?? ''));
    }

    /**
     * Sanitize an email address.
     */
    public static function email(mixed $value): string
    {
        return sanitize_email((string) ($value ?? ''));
    }

    /**
     * Sanitize a URL.
     */
    public static function url(mixed $value): string
    {
        return esc_url_raw((string) ($value ?? ''));
    }

    /**
     * Sanitize an integer.
     */
    public static function integer(mixed $value): int
    {
        return (int) filter_var($value, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
    }

    /**
     * Sanitize a boolean.
     */
    public static function boolean(mixed $value): bool
    {
        return (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Sanitize HTML content (wp_kses_post).
     */
    public static function html(mixed $value): string
    {
        return wp_kses_post((string) ($value ?? ''));
    }

    /**
     * Sanitize a slug / filename-safe string.
     */
    public static function slug(mixed $value): string
    {
        $slug = sanitize_title((string) ($value ?? ''));
        return preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));
    }

    /**
     * Sanitize a file path — remove traversal sequences.
     */
    public static function path(mixed $value): string
    {
        $path = str_replace(['../', '..\\', '%00', "\0"], '', (string) ($value ?? ''));
        return wp_normalize_path($path);
    }

    /**
     * Sanitize an array of values recursively.
     */
    public static function array(array $values, callable $sanitizer): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $result[self::text($key)] = self::array($value, $sanitizer);
            } else {
                $result[self::text($key)] = $sanitizer($value);
            }
        }
        return $result;
    }

    /**
     * Sanitize a JSON-decoded payload.
     */
    public static function json(mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return $decoded !== null ? $decoded : $value;
        }
        return $value;
    }

    /**
     * Sanitize a SQL identifier (table/column name) — allow only safe chars.
     */
    public static function sqlIdentifier(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_`]/', '', $value);
    }
}
