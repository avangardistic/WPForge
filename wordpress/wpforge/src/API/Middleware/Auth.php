<?php

namespace WPForge\API\Middleware;

use WPForge\Auth\Authenticator;

/**
 * Authentication middleware — runs before route callbacks.
 */
class Auth
{
    private Authenticator $authenticator;

    public function __construct(Authenticator $authenticator)
    {
        $this->authenticator = $authenticator;
    }

    /**
     * Middleware callable compatible with Router::addMiddleware().
     */
    public function __invoke(\WP_REST_Request $request): bool|\WP_Error|\WP_REST_Response
    {
        $user = $this->authenticator->authenticate($request);

        if ($user === null) {
            if (is_user_logged_in()) {
                return true; // Cookie auth is fine.
            }
            return new \WP_Error(
                'wpforge_unauthorized',
                'Authentication required. Provide Application Password or Bearer token.',
                ['status' => 401]
            );
        }

        // Set the authenticated user for downstream code.
        wp_set_current_user($user->ID);

        return true;
    }
}
