<?php

namespace WPForge\API;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Users routes for WPForge API
 */
class UsersRoutes extends BaseRoutes
{
    public static function register(): void
    {
        $instance = new self();

        register_rest_route($instance->namespace, '/users', [
            'methods' => 'GET',
            'callback' => [$instance, 'getUsers'],
            'permission_callback' => [$instance, 'checkPermission'],
        ]);

        register_rest_route($instance->namespace, '/users/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$instance, 'getUser'],
            'permission_callback' => [$instance, 'checkPermission'],
        ]);

        register_rest_route($instance->namespace, '/users/me', [
            'methods' => 'GET',
            'callback' => [$instance, 'getCurrentUser'],
            'permission_callback' => [$instance, 'checkAuth'],
        ]);
    }

    public function getUsers(WP_REST_Request $request): WP_REST_Response
    {
        $args = [
            'number' => min((int) ($request->get_param('per_page') ?? 20), 100),
            'paged' => (int) ($request->get_param('page') ?? 1),
            'role' => $request->get_param('role') ?? '',
        ];

        $query = new \WP_User_Query($args);
        $users = [];

        foreach ($query->get_results() as $user) {
            $users[] = $this->formatUser($user);
        }

        return $this->successResponse([
            'users' => $users,
            'total' => $query->get_total(),
            'page' => $args['paged'],
        ]);
    }

    public function getUser(WP_REST_Request $request): WP_REST_Response
    {
        $user_id = (int) $request->get_param('id');
        $user = get_userdata($user_id);

        if (!$user) {
            return $this->errorResponse('user_not_found', 'User not found.', 404);
        }

        return $this->successResponse($this->formatUser($user));
    }

    public function getCurrentUser(): WP_REST_Response
    {
        $user = wp_get_current_user();
        return $this->successResponse($this->formatUser($user));
    }

    private function formatUser(\WP_User $user): array
    {
        return [
            'id' => $user->ID,
            'username' => $user->user_login,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'roles' => $user->roles,
            'capabilities' => array_keys(array_filter($user->allcaps)),
            'registered' => $user->user_registered,
            'url' => $user->user_url,
        ];
    }

    public function checkPermission(): bool|WP_Error
    {
        $auth = $this->checkAuth();
        if (is_wp_error($auth)) {
            return $auth;
        }
        return $this->checkCapability('list_users');
    }
}
