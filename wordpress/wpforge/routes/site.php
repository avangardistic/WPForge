<?php
use WPForge\API\Response;
use WPForge\WordPress\SiteInspector;

$ns = WPFORGE_NAMESPACE;
$inspector = new SiteInspector();

register_rest_route($ns, '/site', [
    'methods'             => 'GET',
    'callback'            => fn($request) => Response::success($inspector->inspect()),
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/site/structure', [
    'methods'             => 'GET',
    'callback'            => fn($request) => Response::success($inspector->getStructure()),
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/site/routes', [
    'methods'             => 'GET',
    'callback'            => fn($request) => Response::success($inspector->getRoutes()),
    'permission_callback' => 'is_user_logged_in',
]);
