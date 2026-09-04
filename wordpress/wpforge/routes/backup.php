<?php
use WPForge\API\Response;
use WPForge\Backup\Manager;

$ns = WPFORGE_NAMESPACE;
$backupManager = new Manager(WPFORGE_BACKUP_DIR);

register_rest_route($ns, '/backup', [
    'methods'             => 'POST',
    'callback'            => function ($request) use ($backupManager) {
        if (!current_user_can('manage_options')) {
            return Response::error('FORBIDDEN', 'Permission denied', 403);
        }
        $type = $request->get_param('type') ?: 'database';
        try {
            $result = $backupManager->create($type);
            return Response::success(['success' => true, 'backup' => $result, 'message' => 'Backup created successfully'], 201);
        } catch (\Exception $e) {
            return Response::error('BACKUP_FAILED', $e->getMessage(), 500);
        }
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/backup', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($backupManager) {
        $backups = $backupManager->listBackups();
        return Response::success(['backups' => $backups, 'count' => count($backups)]);
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/backup/(?P<id>[a-zA-Z0-9_]+)', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($backupManager) {
        $backup = $backupManager->getBackupInfo($request['id']);
        return $backup ? Response::success($backup) : Response::error('NOT_FOUND', 'Backup not found', 404);
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/backup/(?P<id>[a-zA-Z0-9_]+)', [
    'methods'             => 'DELETE',
    'callback'            => function ($request) use ($backupManager) {
        if (!current_user_can('manage_options')) {
            return Response::error('FORBIDDEN', 'Permission denied', 403);
        }
        try {
            $backupManager->deleteBackup($request['id']);
            return Response::success(['deleted' => true, 'backup_id' => $request['id']]);
        } catch (\Exception $e) {
            return Response::error('DELETE_FAILED', $e->getMessage(), 500);
        }
    },
    'permission_callback' => 'is_user_logged_in',
]);
