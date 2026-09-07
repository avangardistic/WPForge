<?php

namespace WPForge\API;

/**
 * Permission-callback factories for REST route registration.
 *
 * WordPress treats any non-empty string returned from a permission_callback as
 * truthy, and a bare `false` yields an opaque 401 with no explanation. These
 * helpers instead return a closure that resolves the real authentication state
 * and the required capabilities, returning a WP_Error with an accurate status
 * (401 when unauthenticated, 403 when authenticated but under-privileged) so a
 * client can tell the two apart.
 *
 * Capability gating lives here, at the permission layer, so an unauthorised
 * caller is rejected before the route handler runs. Handlers keep their own
 * per-object checks (e.g. `edit_post` for a specific id) as defence in depth.
 */
class Permissions
{
    /**
     * Require an authenticated user with every listed capability.
     *
     * @param string ...$capabilities Capabilities the user must all hold.
     */
    public static function can(string ...$capabilities): callable
    {
        return static function () use ($capabilities) {
            if (!is_user_logged_in()) {
                return new \WP_Error(
                    'wpforge_unauthenticated',
                    'Authentication required.',
                    ['status' => 401]
                );
            }

            foreach ($capabilities as $capability) {
                if (!current_user_can($capability)) {
                    return new \WP_Error(
                        'wpforge_forbidden',
                        'You do not have the required capability for this operation.',
                        ['status' => 403]
                    );
                }
            }

            return true;
        };
    }

    /**
     * Require only that a user is authenticated.
     *
     * Use for endpoints whose handler performs its own finer-grained checks, or
     * that only expose the current user's own data.
     */
    public static function authenticated(): callable
    {
        return static function () {
            if (!is_user_logged_in()) {
                return new \WP_Error(
                    'wpforge_unauthenticated',
                    'Authentication required.',
                    ['status' => 401]
                );
            }

            return true;
        };
    }
}
