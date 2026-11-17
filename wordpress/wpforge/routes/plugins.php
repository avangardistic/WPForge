<?php

use WPForge\API\Response;
use WPForge\Core\Config;
use WPForge\WordPress\PluginManager;

$ns = WPFORGE_NAMESPACE;
$pm = new PluginManager();
$config = new Config();

register_rest_route($ns, '/plugins', [
    'methods'             => 'GET',
    'callback'            => function ($request) use ($pm) {
        return Response::success($pm->getPlugins());
    },
    'permission_callback' => \WPForge\API\Permissions::can('activate_plugins'),
]);

register_rest_route($ns, '/plugins/activate', [
    'methods'             => 'POST',
    'callback'            => function ($request) use ($pm, $config) {
        if (!current_user_can('activate_plugins')) {
            return Response::error('FORBIDDEN', 'Permission denied', 403);
        }
        // S3: allow_plugin_installation (developer_mode-gated) must be on before
        // plugin state changes are honoured. Naming kept for backward compat:
        // this flag gates activate/deactivate — there is no /plugins/install route.
        if (!$config->allowPluginInstallation()) {
            return Response::error(
                'PLUGIN_INSTALLATION_DISABLED',
                'Plugin activation/deactivation is disabled. Enable developer_mode and allow_plugin_installation.',
                403
            );
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
    'permission_callback' => \WPForge\API\Permissions::can('activate_plugins'),
]);

register_rest_route($ns, '/plugins/deactivate', [
    'methods'             => 'POST',
    'callback'            => function ($request) use ($pm, $config) {
        if (!current_user_can('activate_plugins')) {
            return Response::error('FORBIDDEN', 'Permission denied', 403);
        }
        // S3: same gate as activate — see note on allow_plugin_installation naming.
        if (!$config->allowPluginInstallation()) {
            return Response::error(
                'PLUGIN_INSTALLATION_DISABLED',
                'Plugin activation/deactivation is disabled. Enable developer_mode and allow_plugin_installation.',
                403
            );
        }
        $plugin = $request->get_param('plugin');
        if (!$plugin) {
            return Response::error('MISSING_PLUGIN', 'Plugin path is required', 400);
        }
        $pm->deactivatePlugin($plugin);
        return Response::success(['deactivated' => true]);
    },
    'permission_callback' => \WPForge\API\Permissions::can('activate_plugins'),
]);
