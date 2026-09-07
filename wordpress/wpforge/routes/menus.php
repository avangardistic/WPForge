<?php

use WPForge\API\Response;
use WPForge\WordPress\MenuManager;

$ns = WPFORGE_NAMESPACE;
$mm = new MenuManager();

register_rest_route($ns, '/menus', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($mm) {
        return Response::success($mm->getMenus());
    },
    'permission_callback' => \WPForge\API\Permissions::can('edit_theme_options'),
]);

register_rest_route($ns, '/menus/(?P<id>\d+)', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($mm) {
        $menu = $mm->getMenu((int) $request['id']);
        return $menu ? Response::success($menu) : Response::error('NOT_FOUND', 'Menu not found', 404);
    },
    'permission_callback' => \WPForge\API\Permissions::can('edit_theme_options'),
]);

register_rest_route($ns, '/menus/locations', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($mm) {
        return Response::success($mm->getLocations());
    },
    'permission_callback' => \WPForge\API\Permissions::can('edit_theme_options'),
]);
