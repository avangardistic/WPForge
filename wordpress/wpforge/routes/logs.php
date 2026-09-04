<?php

namespace WPForge\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Logs routes for WPForge API
 */
class LogsRoutes extends BaseRoutes
{
    public static function register(): void
    {
        $instance = new self();

        register_rest_route($instance->namespace, '/logs', [
            'methods' => 'GET',
            'callback' => [$instance, 'getLogs'],
            'permission_callback' => [$instance, 'checkPermission'],
        ]);

        register_rest_route($instance->namespace, '/logs/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$instance, 'getLog'],
            'permission_callback' => [$instance, 'checkPermission'],
        ]);

        register_rest_route($instance->namespace, '/logs/clear', [
            'methods' => 'DELETE',
            'callback' => [$instance, 'clearLogs'],
            'permission_callback' => [$instance, 'checkPermission'],
        ]);
    }

    public function getLogs(WP_REST_Request $request): WP_REST_Response
    {
        $args = [
            'page' => (int) ($request->get_param('page') ?? 1),
            'per_page' => min((int) ($request->get_param('per_page') ?? 50), 100),
            'user_id' => $request->get_param('user_id') ? (int) $request->get_param('user_id') : null,
            'operation' => $request->get_param('operation') ?? null,
            'success' => $request->get_param('success') !== null ? (bool) $request->get_param('success') : null,
            'search' => $request->get_param('search') ?? null,
        ];

        $logger = new \WPForge\Logging\Logger();
        $result = $logger->getLogs($args);

        return $this->successResponse($result);
    }

    public function getLog(WP_REST_Request $request): WP_REST_Response
    {
        $log_id = (int) $request->get_param('id');
        
        $logger = new \WPForge\Logging\Logger();
        $log = $logger->getLog($log_id);

        if (!$log) {
            return $this->errorResponse('log_not_found', 'Log entry not found.', 404);
        }

        return $this->successResponse($log);
    }

    public function clearLogs(WP_REST_Request $request): WP_REST_Response
    {
        $confirm = $request->get_param('confirm');
        
        if ($confirm !== true) {
            return $this->errorResponse('confirmation_required', 'Must set confirm=true to clear logs.', 400);
        }

        $logger = new \WPForge\Logging\Logger();
        $logger->clearAll();

        $this->logMutation('clear_logs', 'all', true, 200);
        return $this->successResponse(['cleared' => true]);
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
