<?php
use WPForge\API\Response;
use WPForge\Filesystem\Manager;
use WPForge\Core\Config;

$ns = WPFORGE_NAMESPACE;
$config = new Config();
$root = $config->getFilesystemRoot();
$fsManager = new Manager($root);

register_rest_route($ns, '/files/list', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($fsManager) {
        $path = $request->get_param('path') ?: '';
        try {
            $listing = $fsManager->listDirectory($path);
            return Response::success(['path' => $path, 'files' => $listing, 'count' => count($listing)]);
        } catch (\Exception $e) {
            return Response::error('LIST_FAILED', $e->getMessage(), 400);
        }
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/files/read', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($fsManager) {
        $path = $request->get_param('path');
        if (!$path) {
            return Response::error('MISSING_PATH', 'Path parameter is required', 400);
        }
        try {
            return Response::success($fsManager->readFile($path));
        } catch (\Exception $e) {
            return Response::error('READ_FAILED', $e->getMessage(), 400);
        }
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/files/write', [
    'methods'             => 'POST',
    'callback'            => function ($request) use ($fsManager, $config) {
        if (!current_user_can('edit_files')) {
            return Response::error('FORBIDDEN', 'Permission denied', 403);
        }
        if (!$config->allowFilesystemWrites()) {
            return Response::error('WRITES_DISABLED', 'File writes are disabled', 403);
        }
        $data = $request->get_json_params();
        if (empty($data['path']) || !isset($data['content'])) {
            return Response::error('MISSING_DATA', 'Path and content are required', 400);
        }
        if (($request->get_param('dry_run') ?: '') === 'true') {
            return Response::success([
                'dry_run' => true,
                'path'    => $data['path'],
                'size'    => strlen($data['content']),
                'message' => 'Dry run — no file was written',
            ]);
        }
        try {
            return Response::success($fsManager->writeFile($data['path'], $data['content']));
        } catch (\Exception $e) {
            return Response::error('WRITE_FAILED', $e->getMessage(), 500);
        }
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/files/delete', [
    'methods'             => 'DELETE',
    'callback'            => function ($request) use ($fsManager, $config) {
        if (!current_user_can('edit_files')) {
            return Response::error('FORBIDDEN', 'Permission denied', 403);
        }
        $path = $request->get_param('path');
        if (!$path) {
            return Response::error('MISSING_PATH', 'Path parameter is required', 400);
        }
        try {
            return Response::success($fsManager->deleteFile($path));
        } catch (\Exception $e) {
            return Response::error('DELETE_FAILED', $e->getMessage(), 500);
        }
    },
    'permission_callback' => 'is_user_logged_in',
]);
