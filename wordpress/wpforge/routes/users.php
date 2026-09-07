<?php

use WPForge\API\Response;
use WPForge\WordPress\UserManager;

$ns = WPFORGE_NAMESPACE;
$um = new UserManager();

register_rest_route($ns, '/users', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($um) {
        if (!current_user_can('list_users')) {
            return Response::error('FORBIDDEN', 'Permission denied', 403);
        }
        $perPage = (int) ($request->get_param('per_page') ?: 20);
        $page = (int) ($request->get_param('page') ?: 1);
        $result = $um->getUsers([
            'number' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ]);
        return Response::success($result);
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/users/(?P<id>\d+)', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($um) {
        $user = $um->getUser((int) $request['id']);
        return $user ? Response::success($user) : Response::error('NOT_FOUND', 'User not found', 404);
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/users', [
    'methods'             => 'POST',
    'callback'            => function ($request) use ($um) {
        if (!current_user_can('create_users')) {
            return Response::error('FORBIDDEN', 'Permission denied', 403);
        }
        try {
            $user = $um->createUser($request->get_json_params());
            return Response::success($user, 201);
        } catch (\Exception $e) {
            return Response::error('CREATE_FAILED', $e->getMessage(), 500);
        }
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/users/(?P<id>\d+)', [
    'methods'             => 'PUT, PATCH',
    'callback'            => function ($request) use ($um) {
        if (!current_user_can('edit_user', (int) $request['id'])) {
            return Response::error('FORBIDDEN', 'Permission denied', 403);
        }
        try {
            $user = $um->updateUser((int) $request['id'], $request->get_json_params());
            return Response::success($user);
        } catch (\Exception $e) {
            return Response::error('UPDATE_FAILED', $e->getMessage(), 500);
        }
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/users/(?P<id>\d+)', [
    'methods'             => 'DELETE',
    'callback'            => function ($request) use ($um) {
        if (!current_user_can('delete_user', (int) $request['id'])) {
            return Response::error('FORBIDDEN', 'Permission denied', 403);
        }
        $result = $um->deleteUser((int) $request['id']);
        return $result
            ? Response::success(['deleted' => true, 'id' => (int) $request['id']])
            : Response::error('DELETE_FAILED', 'Failed to delete user', 500);
    },
    'permission_callback' => 'is_user_logged_in',
]);
