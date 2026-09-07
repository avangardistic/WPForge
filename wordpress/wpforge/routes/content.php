<?php

use WPForge\API\Response;
use WPForge\WordPress\PostManager;

$ns = WPFORGE_NAMESPACE;
$pm = new PostManager();

// --- POSTS ---

register_rest_route($ns, '/posts', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($pm) {
        $args = [
            'posts_per_page' => (int) ($request->get_param('per_page') ?: 20),
            'paged'          => (int) ($request->get_param('page') ?: 1),
            'post_status'    => $request->get_param('status') ?: 'publish',
        ];
        if ($request->get_param('search')) {
            $args['s'] = $request->get_param('search');
        }
        $result = $pm->getPosts('post', $args);
        return Response::paginated($result['posts'], $result['total'], $result['page'], $result['per_page']);
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/posts/(?P<id>\d+)', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($pm) {
        $post = $pm->getPost((int) $request['id']);
        return $post ? Response::success($post) : Response::error('NOT_FOUND', 'Post not found', 404);
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/posts', [
    'methods'             => 'POST',
    'callback'            => function ($request) use ($pm) {
        if (!current_user_can('publish_posts')) {
            return Response::error('FORBIDDEN', 'You do not have permission to create posts', 403);
        }
        try {
            $data = $request->get_json_params();
            $post = $pm->createPost($data);
            return Response::success($post, 201);
        } catch (\Exception $e) {
            return Response::error('CREATE_FAILED', $e->getMessage(), 500);
        }
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/posts/(?P<id>\d+)', [
    'methods'             => 'PUT, PATCH',
    'callback'            => function ($request) use ($pm) {
        $id = (int) $request['id'];
        if (!current_user_can('edit_post', $id)) {
            return Response::error('FORBIDDEN', 'You do not have permission to edit this post', 403);
        }
        try {
            $post = $pm->updatePost($id, $request->get_json_params());
            return Response::success($post);
        } catch (\Exception $e) {
            return Response::error('UPDATE_FAILED', $e->getMessage(), 500);
        }
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/posts/(?P<id>\d+)', [
    'methods'             => 'DELETE',
    'callback'            => function ($request) use ($pm) {
        $id = (int) $request['id'];
        if (!current_user_can('delete_post', $id)) {
            return Response::error('FORBIDDEN', 'You do not have permission to delete this post', 403);
        }
        $force = ($request->get_param('force') === 'true');
        $result = $pm->deletePost($id, $force);
        return $result
            ? Response::success(['deleted' => true, 'id' => $id])
            : Response::error('DELETE_FAILED', 'Failed to delete post', 500);
    },
    'permission_callback' => 'is_user_logged_in',
]);

// --- PAGES ---

register_rest_route($ns, '/pages', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($pm) {
        $args = [
            'posts_per_page' => (int) ($request->get_param('per_page') ?: 20),
            'paged'          => (int) ($request->get_param('page') ?: 1),
            'post_status'    => $request->get_param('status') ?: 'publish',
        ];
        if ($request->get_param('search')) {
            $args['s'] = $request->get_param('search');
        }
        $result = $pm->getPosts('page', $args);
        return Response::paginated($result['posts'], $result['total'], $result['page'], $result['per_page']);
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/pages/(?P<id>\d+)', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($pm) {
        $post = $pm->getPost((int) $request['id']);
        return $post ? Response::success($post) : Response::error('NOT_FOUND', 'Page not found', 404);
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/pages', [
    'methods'             => 'POST',
    'callback'            => function ($request) use ($pm) {
        if (!current_user_can('publish_pages')) {
            return Response::error('FORBIDDEN', 'You do not have permission to create pages', 403);
        }
        try {
            $data = $request->get_json_params();
            $data['post_type'] = 'page';
            $post = $pm->createPost($data);
            return Response::success($post, 201);
        } catch (\Exception $e) {
            return Response::error('CREATE_FAILED', $e->getMessage(), 500);
        }
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/pages/(?P<id>\d+)', [
    'methods'             => 'PUT, PATCH',
    'callback'            => function ($request) use ($pm) {
        $id = (int) $request['id'];
        if (!current_user_can('edit_post', $id)) {
            return Response::error('FORBIDDEN', 'Permission denied', 403);
        }
        try {
            $post = $pm->updatePost($id, $request->get_json_params());
            return Response::success($post);
        } catch (\Exception $e) {
            return Response::error('UPDATE_FAILED', $e->getMessage(), 500);
        }
    },
    'permission_callback' => 'is_user_logged_in',
]);
