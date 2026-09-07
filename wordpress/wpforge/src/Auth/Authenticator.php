<?php

namespace WPForge\Auth;

/**
 * Multi-method authenticator — Application Passwords (Basic) and Bearer tokens.
 */
class Authenticator
{
    private TokenManager $tokenManager;

    public function __construct()
    {
        $this->tokenManager = new TokenManager();
    }

    /**
     * Attempt to authenticate a request. Returns WP_User on success, null on failure.
     */
    public function authenticate(\WP_REST_Request $request): ?\WP_User
    {
        // 1. Application Password (Basic Auth)
        $user = $this->authenticateApplicationPassword($request);
        if ($user instanceof \WP_User) {
            return $user;
        }

        // 2. Bearer token
        $user = $this->authenticateBearerToken($request);
        if ($user instanceof \WP_User) {
            return $user;
        }

        return null;
    }

    /**
     * Validate Basic Auth credentials using WordPress Application Passwords.
     */
    private function authenticateApplicationPassword(\WP_REST_Request $request): ?\WP_User
    {
        $authHeader = $request->get_header('Authorization');
        if (!$authHeader || strpos($authHeader, 'Basic ') !== 0) {
            // Fallback to $_SERVER for some server configs.
            if (isset($_SERVER['PHP_AUTH_USER'])) {
                $username = sanitize_user(wp_unslash($_SERVER['PHP_AUTH_USER']));
                // A password must not be passed through sanitize_*(); that would alter it.
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- see note above.
                $password = wp_unslash($_SERVER['PHP_AUTH_PW'] ?? '');
            } else {
                return null;
            }
        } else {
            $decoded = base64_decode(substr($authHeader, 6), true);
            if ($decoded === false) {
                return null;
            }
            $parts = explode(':', $decoded, 2);
            if (count($parts) !== 2) {
                return null;
            }
            $username = sanitize_user($parts[0]);
            $password = $parts[1];
        }

        if (empty($username) || empty($password)) {
            return null;
        }

        if (!function_exists('wp_authenticate_application_password')) {
            return null;
        }

        $user = wp_authenticate_application_password(null, $username, $password);

        return is_wp_error($user) ? null : $user;
    }

    /**
     * Validate a Bearer token from the Authorization header.
     */
    private function authenticateBearerToken(\WP_REST_Request $request): ?\WP_User
    {
        $authHeader = $request->get_header('Authorization');
        if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
            return null;
        }

        return $this->authenticateBearerTokenString(substr($authHeader, 7));
    }

    /**
     * Validate a raw Bearer token string (used by the global REST auth filter).
     */
    public function authenticateBearerTokenString(string $token): ?\WP_User
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        return $this->tokenManager->validateToken($token);
    }
}
