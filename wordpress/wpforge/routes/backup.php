<?php

namespace WPForge\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPForge\Backup\BackupService;

/**
 * Backup routes for WPForge API
 */
class BackupRoutes extends BaseRoutes
{
    private BackupService $backup;

    public function __construct()
    {
        parent::__construct();
        $this->backup = new BackupService();
    }

    public static function register(): void
    {
        $instance = new self();

        register_rest_route($instance->namespace, '/backup', [
            'methods' => 'POST',
            'callback' => [$instance, 'createBackup'],
            'permission_callback' => [$instance, 'checkPermission'],
        ]);

        register_rest_route($instance->namespace, '/backups', [
            'methods' => 'GET',
            'callback' => [$instance, 'listBackups'],
            'permission_callback' => [$instance, 'checkPermission'],
        ]);

        register_rest_route($instance->namespace, '/backups/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$instance, 'getBackup'],
            'permission_callback' => [$instance, 'checkPermission'],
        ]);

        register_rest_route($instance->namespace, '/backups/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => 'DELETE',
            'callback' => [$instance, 'deleteBackup'],
            'permission_callback' => [$instance, 'checkPermission'],
        ]);
    }

    public function createBackup(WP_REST_Request $request): WP_REST_Response
    {
        $scope = $request->get_param('scope') ?? 'database';
        $name = $request->get_param('name') ?? null;

        $result = $this->backup->createBackup($scope, $name);

        if (is_wp_error($result)) {
            $this->logMutation('create_backup', $scope, false, 400, $result->get_error_code());
            return $this->errorResponse($result->get_error_code(), $result->get_error_message(), 400);
        }

        $this->logMutation('create_backup', $result['backup_id'], true, 201);
        return $this->successResponse($result, 201);
    }

    public function listBackups(WP_REST_Request $request): WP_REST_Response
    {
        $backups = $this->backup->listBackups();
        return $this->successResponse(['backups' => $backups]);
    }

    public function getBackup(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $backup = $this->backup->getBackup($id);

        if (!$backup) {
            return $this->errorResponse('backup_not_found', 'Backup not found.', 404);
        }

        return $this->successResponse($backup);
    }

    public function deleteBackup(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $result = $this->backup->deleteBackup($id);

        if (is_wp_error($result)) {
            return $this->errorResponse($result->get_error_code(), $result->get_error_message(), 400);
        }

        $this->logMutation('delete_backup', $id, true, 200);
        return $this->successResponse(['deleted' => true]);
    }

    public function checkPermission(): bool|WP_Error
    {
        $auth = $this->checkAuth();
        if (is_wp_error($auth)) {
            return $auth;
        }
        return $this->checkCapability('manage_options');
    }
}
