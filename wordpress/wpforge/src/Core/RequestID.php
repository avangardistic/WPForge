<?php

namespace WPForge\Core;

/**
 * Unique request ID generator — ensures every request can be correlated.
 */
class RequestID
{
    private static ?string $current = null;

    /**
     * Get (or generate) the request ID for the current request.
     */
    public static function get(): string
    {
        if (self::$current !== null) {
            return self::$current;
        }

        self::$current = self::generate();
        return self::$current;
    }

    /**
     * Force-reset the current request ID (useful in tests).
     */
    public static function reset(): void
    {
        self::$current = null;
    }

    /**
     * Generate a new unique request ID.
     */
    public static function generate(): string
    {
        return 'wf_' . bin2hex(random_bytes(16));
    }
}
