<?php

namespace WPForge\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPForge\Filesystem\FilesystemService;
use WPForge\Core\Config;

/**
 * Filesystem routes for WPForge API
 */
class FilesystemRoutes extends BaseRoutes
{
    private FilesystemService $filesystem;
    private Config $config;

    public function __construct()
    {
        parent::__construct();
        $this->filesystem = new FilesystemService();
        $this->config = new Config();
    }

    public static function register(): void
    {
        $instance = new self();

        // GET /filesystem/read - Read file content
        register_rest_route($instance->namespace, '/filesystem/read', [
            'methods' => 'GET',
            'callback' => [$instance, 'readFile'],
            'permission_callback' => [$instance, 'checkReadPermission'],
        ]);

        // POST /filesystem/write - Write file content
        register_rest_route($instance->namespace, '/filesystem/write', [
            'methods' => 'POST',
            'callback' => [$instance, 'writeFile'],
            'permission_callback' => [$instance, 'checkWritePermission'],
        ]);

        // DELETE /filesystem/delete - Delete file
        register_rest_route($instance->namespace, '/filesystem/delete', [
            'methods' => 'DELETE',
            'callback' => [$instance, 'deleteFile'],
            'permission_callback' => [$instance, 'checkWritePermission'],
        ]);

        // GET /filesystem/list - List directory
        register_rest_route($instance->namespace, '/filesystem/list', [
            'methods' => 'GET',
            'callback' => [$instance, 'listDirectory'],
            'permission_callback' => [$instance, 'checkReadPermission'],
        ]);

        // POST /filesystem/create-dir - Create directory
        register_rest_route($instance->namespace, '/filesystem/create-dir', [
            'methods' => 'POST',
            'callback' => [$instance, 'createDirectory'],
            'permission_callback' => [$instance, 'checkWritePermission'],
        ]);
    }

    public function readFile(WP_REST_Request $request): WP_REST_Response
    {
        $path = $request->get_param('path');
        
        if (empty($path)) {
            return $this->errorResponse('missing_path', 'Path parameter is required.', 400);
        }

        $result = $this->filesystem->readFile($path);

        if (is_wp_error($result)) {
            return $this->errorResponse($result->get_error_code(), $result->get_error_message(), 400);
        }

        return $this->successResponse($result);
    }

    public function writeFile(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->config->allowFilesystemWrites()) {
            return $this->errorResponse('filesystem_writes_disabled', 'Filesystem writes are disabled. Enable developer mode.', 403);
        }

        $path = $request->get_param('path');
        $content = $request->get_param('content');
        $dry_run = $request->get_param('dry_run') ?? false;

        if (empty($path)) {
            return $this->errorResponse('missing_path', 'Path parameter is required.', 400);
        }

        if (!isset($content)) {
            return $this->errorResponse('missing_content', 'Content parameter is required.', 400);
        }

        // Dry run mode
        if ($dry_run) {
            $exists = file_exists($this->filesystem->normalizePath($path));
            return $this->successResponse([
                'dry_run' => true,
                'would_write' => $path,
                'file_exists' => $exists,
                'content_length' => strlen($content),
            ]);
        }

        $result = $this->filesystem->writeFile($path, $content);

        if (is_wp_error($result)) {
            $this->logMutation('filesystem_write', $path, false, 400, $result->get_error_code());
            return $this->errorResponse($result->get_error_code(), $result->get_error_message(), 400);
        }

        $this->logMutation('filesystem_write', $path, true, 200, null, [
            'old_hash' => $result['old_hash'] ?? null,
            'new_hash' => $result['new_hash'],
        ]);

        return $this->successResponse($result);
    }

    public function deleteFile(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->config->allowFilesystemWrites()) {
            return $this->errorResponse('filesystem_writes_disabled', 'Filesystem writes are disabled.', 403);
        }

        $path = $request->get_param('path');
        $dry_run = $request->get_param('dry_run') ?? false;

        if (empty($path)) {
            return $this->errorResponse('missing_path', 'Path parameter is required.', 400);
        }

        // Dry run mode
        if ($dry_run) {
            $exists = file_exists($this->filesystem->normalizePath($path));
            return $this->successResponse([
                'dry_run' => true,
                'would_delete' => $path,
                'file_exists' => $exists,
            ]);
        }

        $result = $this->filesystem->deleteFile($path);

        if (is_wp_error($result)) {
            $this->logMutation('filesystem_delete', $path, false, 400, $result->get_error_code());
            return $this->errorResponse($result->get_error_code(), $result->get_error_message(), 400);
        }

        $this->logMutation('filesystem_delete', $path, true, 200);
        return $this->successResponse($result);
    }

    public function listDirectory(WP_REST_Request $request): WP_REST_Response
    {
        $path = $request->get_param('path') ?? '';
        $recursive = $request->get_param('recursive') ?? false;
        $include_files = $request->get_param('include_files') ?? true;
        $include_dirs = $request->get_param('include_dirs') ?? true;

        $result = $this->filesystem->listDirectory($path, [
            'recursive' => $recursive,
            'include_files' => $include_files,
            'include_dirs' => $include_dirs,
        ]);

        if (is_wp_error($result)) {
            return $this->errorResponse($result->get_error_code(), $result->get_error_message(), 400);
        }

        return $this->successResponse($result);
    }

    public function createDirectory(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->config->allowFilesystemWrites()) {
            return $this->errorResponse('filesystem_writes_disabled', 'Filesystem writes are disabled.', 403);
        }

        $path = $request->get_param('path');

        if (empty($path)) {
            return $this->errorResponse('missing_path', 'Path parameter is required.', 400);
        }

        $result = $this->filesystem->createDirectory($path);

        if (is_wp_error($result)) {
            $this->logMutation('filesystem_mkdir', $path, false, 400, $result->get_error_code());
            return $this->errorResponse($result->get_error_code(), $result->get_error_message(), 400);
        }

        $this->logMutation('filesystem_mkdir', $path, true, 201);
        return $this->successResponse($result, 201);
    }

    public function checkReadPermission(): bool|WP_Error
    {
        return $this->checkCapability('edit_theme_options');
    }

    public function checkWritePermission(): bool|WP_Error
    {
        $auth = $this->checkAuth();
        if (is_wp_error($auth)) {
            return $auth;
        }

        if (!$this->config->allowFilesystemWrites()) {
            return new WP_Error('filesystem_writes_disabled', 'Filesystem writes are disabled.', ['status' => 403]);
        }

        return $this->checkCapability('edit_themes');
    }
}
