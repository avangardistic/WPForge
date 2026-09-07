<?php

namespace WPForge\Auth;

/**
 * WordPress Application Password helper.
 */
class ApplicationPassword
{
    /**
     * Check if Application Passwords are available.
     */
    public static function isAvailable(): bool
    {
        global $wp_version;

        if (version_compare($wp_version, '5.6', '<')) {
            return false;
        }

        return function_exists('wp_authenticate_application_password');
    }

    /**
     * Create an application password for a user.
     */
    public static function create(int $userId, string $name): array|\WP_Error
    {
        if (!function_exists('wp_create_application_password')) {
            return new \WP_Error(
                'app_passwords_unavailable',
                'Application passwords are not available on this WordPress version.',
                ['status' => 501]
            );
        }

        $result = wp_create_application_password($userId, ['name' => sanitize_text_field($name)]);

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'id'       => $result['id'],
            'name'     => $result['name'],
            'password' => $result['password'],
            'created'  => date('Y-m-d H:i:s', $result['created']),
        ];
    }

    /**
     * List application passwords for a user.
     */
    public static function list(int $userId): array|\WP_Error
    {
        if (!function_exists('wp_list_application_passwords')) {
            return new \WP_Error('app_passwords_unavailable', 'Application passwords are not available.', ['status' => 501]);
        }

        $passwords = wp_list_application_passwords($userId);
        return $passwords ?: [];
    }

    /**
     * Delete a specific application password.
     */
    public static function delete(int $userId, string $passwordId): bool|\WP_Error
    {
        if (!function_exists('wp_delete_application_password')) {
            return new \WP_Error('app_passwords_unavailable', 'Application passwords are not available.', ['status' => 501]);
        }

        return wp_delete_application_password($userId, $passwordId);
    }
}
