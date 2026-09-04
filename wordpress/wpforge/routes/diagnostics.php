<?php

namespace WPForge\API;

use WP_REST_Response;
use WPForge\Diagnostics\DiagnosticsService;

/**
 * Diagnostics routes for WPForge API
 */
class DiagnosticsRoutes extends BaseRoutes
{
    private DiagnosticsService $diagnostics;

    public function __construct()
    {
        parent::__construct();
        $this->diagnostics = new DiagnosticsService();
    }

    public static function register(): void
    {
        $instance = new self();

        register_rest_route($instance->namespace, '/diagnostics', [
            'methods' => 'GET',
            'callback' => [$instance, 'getDiagnostics'],
            'permission_callback' => [$instance, 'checkPermission'],
        ]);
    }

    public function getDiagnostics(): WP_REST_Response
    {
        return $this->successResponse($this->diagnostics->run());
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
