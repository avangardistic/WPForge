<?php

namespace WPForge\Security;

/**
 * Nonce manager for CSRF protection on non-REST routes.
 */
class NonceManager
{
    /**
     * Generate a nonce for a given action.
     */
    public static function create(string $action): string
    {
        return wp_create_nonce($action);
    }

    /**
     * Verify a nonce.
     */
    public static function verify(string $nonce, string $action): bool
    {
        return (bool) wp_verify_nonce($nonce, $action);
    }

    /**
     * Check nonce and return a \WP_Error on failure.
     */
    public static function check(string $nonce, string $action): bool|\WP_Error
    {
        if (!self::verify($nonce, $action)) {
            return new \WP_Error('invalid_nonce', 'The security nonce is invalid or has expired.');
        }
        return true;
    }
}
