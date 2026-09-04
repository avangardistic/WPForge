<?php

namespace WPForge\Auth;

/**
 * Authentication service for WPForge
 * 
 * Handles WordPress Application Passwords authentication
 * and optional WPForge-specific token authentication.
 */
class Authentication
{
    /**
     * Check if current request is authenticated
     */
    public function isAuthenticated(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Get current user ID
     */
    public function getCurrentUserId(): int
    {
        return get_current_user_id();
    }

    /**
     * Get current user object
     */
    public function getCurrentUser(): ?\WP_User
    {
        $user_id = $this->getCurrentUserId();
        return $user_id ? wp_get_current_user() : null;
    }

    /**
     * Require authentication - throws exception if not authenticated
     * 
     * @throws \WP_Error if not authenticated
     */
    public function requireAuth(): void
    {
        if (!$this->isAuthenticated()) {
            throw new \WP_Error(
                'wpforge_unauthenticated',
                'Authentication required. Please provide valid credentials.',
                ['status' => 401]
            );
        }
    }

    /**
     * Validate application password credentials
     * 
     * @param string $username WordPress username
     * @param string $password Application password
     * @return \WP_User|\WP_Error
     */
    public function validateApplicationPassword(string $username, string $password): \WP_User|\WP_Error
    {
        $user = wp_authenticate($username, $password);
        
        if (is_wp_error($user)) {
            return new \WP_Error(
                'wpforge_invalid_credentials',
                'Invalid username or password.',
                ['status' => 401]
            );
        }
        
        return $user;
    }

    /**
     * Check if application passwords are enabled
     */
    public function applicationPasswordsEnabled(): bool
    {
        // Application passwords were introduced in WordPress 5.6
        global $wp_version;
        
        if (version_compare($wp_version, '5.6', '<')) {
            return false;
        }
        
        // Check if disabled by filter
        return apply_filters('wp_application_passwords_check_user_enable', true, get_current_user_id());
    }

    /**
     * Generate a new application password for a user
     * 
     * @param int $user_id User ID
     * @param string $name Name for the application password
     * @return array|\WP_Error Array with password details or WP_Error
     */
    public function generateApplicationPassword(int $user_id, string $name): array|\WP_Error
    {
        if (!function_exists('wp_create_application_password')) {
            return new \WP_Error(
                'wpforge_app_passwords_unavailable',
                'Application passwords are not available on this WordPress installation.',
                ['status' => 501]
            );
        }
        
        $result = wp_create_application_password($user_id, [
            'name' => sanitize_text_field($name),
        ]);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return [
            'id' => $result['id'],
            'name' => $result['name'],
            'password' => $result['password'],
            'created' => date('Y-m-d H:i:s', $result['created']),
        ];
    }

    /**
     * List application passwords for a user
     * 
     * @param int $user_id User ID
     * @return array|\WP_Error
     */
    public function listApplicationPasswords(int $user_id): array|\WP_Error
    {
        if (!function_exists('wp_list_application_passwords')) {
            return new \WP_Error(
                'wpforge_app_passwords_unavailable',
                'Application passwords are not available.',
                ['status' => 501]
            );
        }
        
        return wp_list_application_passwords($user_id);
    }

    /**
     * Delete an application password
     * 
     * @param int $user_id User ID
     * @param int $password_id Password ID
     * @return bool|\WP_Error
     */
    public function deleteApplicationPassword(int $user_id, int $password_id): bool|\WP_Error
    {
        if (!function_exists('wp_delete_application_password')) {
            return new \WP_Error(
                'wpforge_app_passwords_unavailable',
                'Application passwords are not available.',
                ['status' => 501]
            );
        }
        
        return wp_delete_application_password($user_id, $password_id);
    }

    /**
     * Get authentication method used in current request
     */
    public function getAuthMethod(): ?string
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        // Check for cookie auth
        if (!empty($_COOKIE[LOGGED_IN_COOKIE])) {
            return 'cookie';
        }
        
        // Check for Basic auth (Application Passwords)
        if (!empty($_SERVER['PHP_AUTH_USER'])) {
            return 'basic';
        }
        
        // Check for Authorization header
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
            if (stripos($auth_header, 'Basic ') === 0) {
                return 'basic';
            } elseif (stripos($auth_header, 'Bearer ') === 0) {
                return 'bearer';
            }
        }
        
        return 'unknown';
    }
}
