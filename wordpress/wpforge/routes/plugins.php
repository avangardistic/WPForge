<?php
use WPForge\API\Response;
use WPForge\WordPress\PluginManager;

$ns = WPFORGE_NAMESPACE;
$pm = new PluginManager();

register_rest_route($ns, '/plugins', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($pm) {
        return Response::success($pm->getPlugins());
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/plugins/activate', [
    'methods'             => 'POST',
    'callback'            => function ($request) use ($pm) {
        if (!current_user_can('activate_plugins')) {
            return Response::error('FORBIDDEN', 'Permission denied', 403);
        }
        $plugin = $request->get_param('plugin');
        if (!$plugin) {
            return Response::error('MISSING_PLUGIN', 'Plugin path is required', 400);
        }
        try {
            $pm->activatePlugin($plugin);
            return Response::success(['activated' => true, 'plugin' => $pm->getPlugin($plugin)]);
        } catch (\Exception $e) {
            return Response::error('ACTIVATE_FAILED', $e->getMessage(), 500);
        }
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/plugins/deactivate', [
    'methods'             => 'POST',
    'callback'            => function ($request) use ($pm) {
        if (!current_user_can('activate_plugins')) {
            return Response::error('FORBIDDEN', 'Permission denied', 403);
        }
        $plugin = $request->get_param('plugin');
        if (!$plugin) {
            return Response::error('MISSING_PLUGIN', 'Plugin path is required', 400);
        }
        $pm->deactivatePlugin($plugin);
        return Response::success(['deactivated' => true]);
    },
    'permission_callback' => 'is_user_logged_in',
]);
