<?php

use WPForge\API\Response;
use WPForge\WordPress\MediaManager;

$ns = WPFORGE_NAMESPACE;
$media = new MediaManager();

register_rest_route($ns, '/media', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($media) {
        $args = [
            'posts_per_page' => (int) ($request->get_param('per_page') ?: 20),
            'paged'          => (int) ($request->get_param('page') ?: 1),
        ];
        if ($request->get_param('search')) {
            $args['s'] = $request->get_param('search');
        }
        if ($request->get_param('type')) {
            $args['post_mime_type'] = $request->get_param('type');
        }
        $result = $media->getMedia($args);
        return Response::paginated($result['media'], $result['total'], $result['page'], $result['per_page']);
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/media/(?P<id>\d+)', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($media) {
        $item = $media->getMediaItem((int) $request['id']);
        return $item ? Response::success($item) : Response::error('NOT_FOUND', 'Media not found', 404);
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/media/upload', [
    'methods'             => 'POST',
    'callback'            => function ($request) use ($media) {
        if (!current_user_can('upload_files')) {
            return Response::error('FORBIDDEN', 'You do not have permission to upload files', 403);
        }
        $url = $request->get_param('url');
        if (!$url) {
            return Response::error('MISSING_URL', 'URL parameter is required', 400);
        }
        try {
            $result = $media->uploadFromUrl($url, [
                'title' => $request->get_param('title') ?: '',
            ]);
            return Response::success($result, 201);
        } catch (\Exception $e) {
            return Response::error('UPLOAD_FAILED', $e->getMessage(), 500);
        }
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/media/(?P<id>\d+)', [
    'methods'             => 'DELETE',
    'callback'            => function ($request) use ($media) {
        if (!current_user_can('delete_post', (int) $request['id'])) {
            return Response::error('FORBIDDEN', 'Permission denied', 403);
        }
        $force = ($request->get_param('force') === 'true');
        $result = $media->deleteMedia((int) $request['id'], $force);
        return $result
            ? Response::success(['deleted' => true, 'id' => (int) $request['id']])
            : Response::error('DELETE_FAILED', 'Failed to delete media', 500);
    },
    'permission_callback' => 'is_user_logged_in',
]);
