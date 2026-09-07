<?php

use WPForge\API\Response;
use WPForge\Diagnostics\HealthReporter;

$ns = WPFORGE_NAMESPACE;
$reporter = new HealthReporter();

register_rest_route($ns, '/diagnostics', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($reporter) {
        return Response::success($reporter->getFullReport());
    },
    'permission_callback' => \WPForge\API\Permissions::can('manage_options'),
]);

register_rest_route($ns, '/diagnostics/quick', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($reporter) {
        return Response::success($reporter->getQuickCheck());
    },
    'permission_callback' => '__return_true',
]);
