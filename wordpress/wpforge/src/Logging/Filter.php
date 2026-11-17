<?php

namespace WPForge\Logging;

/**
 * Log filter — filters and searches log entries.
 */
class Filter
{
    private const SENSITIVE_KEYS = [
        'password',
        'passwd',
        'user_pass',
        'secret',
        'token',
        'api_key',
        'apikey',
        'authorization',
        'cookie',
        'credential',
        'private_key',
        'access_key',
    ];

    /**
     * Recursively redact sensitive keys from arrays (logs, metadata, request payloads).
     */
    public static function redact(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $redacted = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }

            $redacted[$key] = is_array($value) ? self::redact($value) : $value;
        }

        return $redacted;
    }

    private static function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $needle) {
            if (stripos($key, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filter log entries by level.
     */
    public static function byLevel(array $entries, string $level): array
    {
        $level = strtoupper($level);
        return array_filter($entries, fn($e) => strtoupper($e['level'] ?? '') === $level);
    }

    /**
     * Filter log entries by time range.
     */
    public static function byTimeRange(array $entries, string $from, string $to): array
    {
        return array_filter($entries, function ($e) use ($from, $to) {
            $ts = $e['timestamp'] ?? '';
            return $ts >= $from && $ts <= $to;
        });
    }

    /**
     * Search log entries by message content.
     */
    public static function search(array $entries, string $query): array
    {
        $query = strtolower($query);
        return array_filter($entries, function ($e) use ($query) {
            $message = strtolower($e['message'] ?? '');
            return strpos($message, $query) !== false;
        });
    }

    /**
     * Search log entries by context key/value.
     */
    public static function byContext(array $entries, string $key, string $value): array
    {
        return array_filter($entries, function ($e) use ($key, $value) {
            return isset($e['context'][$key]) && $e['context'][$key] == $value;
        });
    }

    /**
     * Limit the number of entries.
     */
    public static function limit(array $entries, int $limit): array
    {
        return array_slice($entries, 0, $limit);
    }

    /**
     * Sort entries by timestamp.
     */
    public static function sort(array $entries, bool $ascending = true): array
    {
        usort($entries, function ($a, $b) use ($ascending) {
            $cmp = strcmp($a['timestamp'] ?? '', $b['timestamp'] ?? '');
            return $ascending ? $cmp : -$cmp;
        });
        return $entries;
    }
}
