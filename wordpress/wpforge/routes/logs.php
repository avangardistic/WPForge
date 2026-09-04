<?php
use WPForge\API\Response;
use WPForge\Logging\Logger;

$ns = WPFORGE_NAMESPACE;
$logger = new Logger();

register_rest_route($ns, '/logs', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($logger) {
        if (!current_user_can('manage_options')) {
            return Response::error('FORBIDDEN', 'Permission denied', 403);
        }
        $args = [
            'page'     => (int) ($request->get_param('page') ?: 1),
            'per_page' => (int) ($request->get_param('limit') ?: 50),
        ];
        if ($request->get_param('operation')) {
            $args['operation'] = $request->get_param('operation');
        }
        if ($request->get_param('search')) {
            $args['search'] = $request->get_param('search');
        }
        return Response::success($logger->getLogs($args));
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/logs/clear', [
    'methods'             => 'DELETE',
    'callback'            => function ($request) use ($logger) {
        if (!current_user_can('manage_options')) {
            return Response::error('FORBIDDEN', 'Permission denied', 403);
        }
        $logger->clearAll();
        return Response::success(['cleared' => true]);
    },
    'permission_callback' => 'is_user_logged_in',
]);
