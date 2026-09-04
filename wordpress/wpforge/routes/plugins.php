<?php

namespace WPForge\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPForge\Core\Config;

/**
 * Plugins routes for WPForge API
 */
class PluginsRoutes extends BaseRoutes
{
    private Config $config;

    public function __construct()
    {
        parent::__construct();
        $this->config = new Config();
    }

    public static function register(): void
    {
        $instance = new self();

        register_rest_route($instance->namespace, '/plugins', [
            'methods' => 'GET',
            'callback' => [$instance, 'getPlugins'],
            'permission_callback' => [$instance, 'checkPermission'],
        ]);

        register_rest_route($instance->namespace, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$instance, 'getPlugin'],
            'permission_callback' => [$instance, 'checkPermission'],
        ]);

        register_rest_route($instance->namespace, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)/activate', [
            'methods' => 'POST',
            'callback' => [$instance, 'activatePlugin'],
            'permission_callback' => [$instance, 'checkActivatePermission'],
        ]);

        register_rest_route($instance->namespace, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)/deactivate', [
            'methods' => 'POST',
            'callback' => [$instance, 'deactivatePlugin'],
            'permission_callback' => [$instance, 'checkActivatePermission'],
        ]);

        register_rest_route($instance->namespace, '/plugins/install', [
            'methods' => 'POST',
            'callback' => [$instance, 'installPlugin'],
            'permission_callback' => [$instance, 'checkInstallPermission'],
        ]);

        register_rest_route($instance->namespace, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)/update', [
            'methods' => 'POST',
            'callback' => [$instance, 'updatePlugin'],
            'permission_callback' => [$instance, 'checkUpdatePermission'],
        ]);
    }

    public function getPlugins(WP_REST_Request $request): WP_REST_Response
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', []);
        
        $plugins = [];
        foreach ($all_plugins as $file => $data) {
            $plugins[] = [
                'file' => $file,
                'slug' => dirname($file),
                'name' => $data['Name'],
                'version' => $data['Version'],
                'author' => $data['Author'],
                'description' => $data['Description'],
                'active' => in_array($file, $active_plugins, true),
                'requires_wp' => $data['RequiresWP'] ?? null,
                'requires_php' => $data['RequiresPHP'] ?? null,
            ];
        }

        return $this->successResponse(['plugins' => $plugins]);
    }

    public function getPlugin(WP_REST_Request $request): WP_REST_Response
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $slug = $request->get_param('slug');
        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', []);

        foreach ($all_plugins as $file => $data) {
            if (dirname($file) === $slug || basename($file, '.php') === $slug) {
                return $this->successResponse([
                    'file' => $file,
                    'slug' => dirname($file),
                    'name' => $data['Name'],
                    'version' => $data['Version'],
                    'author' => $data['Author'],
                    'description' => $data['Description'],
                    'active' => in_array($file, $active_plugins, true),
                ]);
            }
        }

        return $this->errorResponse('plugin_not_found', 'Plugin not found.', 404);
    }

    public function activatePlugin(WP_REST_Request $request): WP_REST_Response
    {
        $slug = $request->get_param('slug');
        $plugin_file = $this->findPluginFile($slug);

        if (!$plugin_file) {
            return $this->errorResponse('plugin_not_found', 'Plugin not found.', 404);
        }

        if (is_plugin_active($plugin_file)) {
            return $this->successResponse(['activated' => false, 'already_active' => true]);
        }

        $result = activate_plugin($plugin_file);

        if (is_wp_error($result)) {
            $this->logMutation('activate_plugin', $slug, false, 400, $result->get_error_code());
            return $this->errorResponse($result->get_error_code(), $result->get_error_message(), 400);
        }

        $this->logMutation('activate_plugin', $slug, true, 200);
        return $this->successResponse(['activated' => true]);
    }

    public function deactivatePlugin(WP_REST_Request $request): WP_REST_Response
    {
        $slug = $request->get_param('slug');
        $plugin_file = $this->findPluginFile($slug);

        if (!$plugin_file) {
            return $this->errorResponse('plugin_not_found', 'Plugin not found.', 404);
        }

        if (!is_plugin_active($plugin_file)) {
            return $this->successResponse(['deactivated' => false, 'already_inactive' => true]);
        }

        deactivate_plugins($plugin_file);

        $this->logMutation('deactivate_plugin', $slug, true, 200);
        return $this->successResponse(['deactivated' => true]);
    }

    public function installPlugin(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->config->allowPluginInstallation()) {
            return $this->errorResponse('plugin_installation_disabled', 'Plugin installation is disabled.', 403);
        }

        $slug = $request->get_param('slug');
        
        if (empty($slug)) {
            return $this->errorResponse('missing_slug', 'Plugin slug is required.', 400);
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $api = plugins_api('plugin_information', ['slug' => $slug]);

        if (is_wp_error($api)) {
            return $this->errorResponse('plugin_info_failed', 'Could not get plugin information.', 400);
        }

        $skin = new \WP_Ajax_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader($skin);
        $result = $upgrader->install($api->download_link);

        if (is_wp_error($result)) {
            $this->logMutation('install_plugin', $slug, false, 400, $result->get_error_code());
            return $this->errorResponse($result->get_error_code(), $result->get_error_message(), 400);
        }

        $this->logMutation('install_plugin', $slug, true, 201);
        return $this->successResponse(['installed' => true, 'name' => $api->name], 201);
    }

    public function updatePlugin(WP_REST_Request $request): WP_REST_Response
    {
        $slug = $request->get_param('slug');
        $plugin_file = $this->findPluginFile($slug);

        if (!$plugin_file) {
            return $this->errorResponse('plugin_not_found', 'Plugin not found.', 404);
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $skin = new \WP_Ajax_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader($skin);
        $result = $upgrader->upgrade($plugin_file);

        if (is_wp_error($result)) {
            $this->logMutation('update_plugin', $slug, false, 400, $result->get_error_code());
            return $this->errorResponse($result->get_error_code(), $result->get_error_message(), 400);
        }

        $this->logMutation('update_plugin', $slug, true, 200);
        return $this->successResponse(['updated' => true]);
    }

    private function findPluginFile(string $slug): ?string
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        
        foreach ($plugins as $file => $data) {
            if (dirname($file) === $slug || basename($file, '.php') === $slug) {
                return $file;
            }
        }

        return null;
    }

    public function checkPermission(): bool|WP_Error
    {
        $auth = $this->checkAuth();
        if (is_wp_error($auth)) {
            return $auth;
        }
        return $this->checkCapability('activate_plugins');
    }

    public function checkActivatePermission(): bool|WP_Error
    {
        return $this->checkPermission();
    }

    public function checkInstallPermission(): bool|WP_Error
    {
        $auth = $this->checkAuth();
        if (is_wp_error($auth)) {
            return $auth;
        }
        
        if (!$this->config->allowPluginInstallation()) {
            return new WP_Error('plugin_installation_disabled', 'Plugin installation is disabled.', ['status' => 403]);
        }
        
        return $this->checkCapability('install_plugins');
    }

    public function checkUpdatePermission(): bool|WP_Error
    {
        return $this->checkCapability('update_plugins');
    }
}
