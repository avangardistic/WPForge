<?php
/**
 * System routes — /status, /capabilities, /environment, /health, /
 */
use WPForge\API\Response;
use WPForge\API\Router;
use WPForge\Core\Config;

// Use global router if available; otherwise create a temporary one.
// Routes are registered via register_rest_route directly for compatibility.
$ns = WPFORGE_NAMESPACE;

register_rest_route($ns, '/status', [
    'methods'             => 'GET',
    'callback'            => function ($request) {
        return Response::success([
            'status'           => 'ok',
            'version'          => WPFORGE_VERSION,
            'wordpress_version'=> get_bloginfo('version'),
            'php_version'      => PHP_VERSION,
            'time'             => current_time('mysql'),
            'site_url'         => get_site_url(),
            'home_url'         => get_home_url(),
        ]);
    },
    'permission_callback' => function() {
        $config = new Config();
        return $config->isPublicStatusEnabled() ? true : is_user_logged_in();
    },
]);

register_rest_route($ns, '/capabilities', [
    'methods'             => 'GET',
    'callback'            => function ($request) {
        if (!is_user_logged_in()) {
            return Response::error('UNAUTHORIZED', 'Authentication required.', 401);
        }
        $user = wp_get_current_user();
        $allCaps = [
            'manage_options', 'edit_pages', 'publish_pages', 'upload_files',
            'activate_plugins', 'install_plugins', 'edit_themes', 'delete_pages',
            'edit_others_pages', 'publish_posts', 'edit_posts', 'delete_posts',
            'edit_files',
        ];
        $capabilities = [];
        foreach ($allCaps as $cap) {
            $capabilities[$cap] = current_user_can($cap);
        }
        return Response::success([
            'user'         => $user->display_name,
            'user_id'      => (int) $user->ID,
            'roles'        => $user->roles,
            'capabilities' => $capabilities,
        ]);
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/environment', [
    'methods'             => 'GET',
    'callback'            => function ($request) {
        global $wpdb;
        return Response::success([
            'wordpress' => [
                'version'             => get_bloginfo('version'),
                'site_url'            => get_site_url(),
                'home_url'            => get_home_url(),
                'admin_url'           => admin_url(),
                'permalink_structure' => get_option('permalink_structure'),
                'wp_debug'            => defined('WP_DEBUG') && WP_DEBUG,
                'multisite'           => is_multisite(),
            ],
            'php' => [
                'version'            => PHP_VERSION,
                'memory_limit'       => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'post_max_size'      => ini_get('post_max_size'),
                'upload_max_filesize'=> ini_get('upload_max_filesize'),
            ],
            'server' => [
                'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
                'https'    => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            ],
            'database' => [
                'type'    => 'MySQL',
                'version' => $wpdb->db_version(),
            ],
        ]);
    },
    'permission_callback' => 'is_user_logged_in',
]);

register_rest_route($ns, '/health', [
    'methods'             => 'GET',
    'callback'            => function ($request) {
        $checks = [
            'wordpress' => ['status' => 'ok', 'details' => 'WordPress core is accessible'],
            'database'  => ['status' => 'ok', 'details' => 'Database connection is working'],
            'filesystem'=> [
                'status'  => is_writable(WP_CONTENT_DIR) ? 'ok' : 'warning',
                'details' => is_writable(WP_CONTENT_DIR) ? 'Content directory is writable' : 'Content directory is not writable',
            ],
            'rest_api'  => ['status' => 'ok', 'details' => 'REST API is available'],
        ];
        $allOk = true;
        foreach ($checks as $check) {
            if ($check['status'] === 'error') { $allOk = false; break; }
        }
        return Response::success([
            'status'    => $allOk ? 'healthy' : 'degraded',
            'checks'    => $checks,
            'timestamp' => current_time('mysql'),
        ]);
    },
    'permission_callback' => function() {
        $config = new Config();
        return $config->isPublicHealthEnabled() ? true : is_user_logged_in();
    },
]);

register_rest_route($ns, '/', [
    'methods'             => 'GET',
    'callback'            => function ($request) {
        return Response::success([
            'name'         => 'WPForge',
            'version'      => WPFORGE_VERSION,
            'api_version'  => 'v1',
            'description'  => 'AI-Powered WordPress Remote Control & Development Bridge',
            'routes' => [
                'system'    => ['/status', '/capabilities', '/environment', '/health'],
                'content'   => ['/posts', '/pages', '/content/{type}'],
                'media'     => ['/media'],
                'elementor' => ['/elementor/status', '/elementor/documents', '/elementor/templates'],
                'filesystem'=> ['/files/list', '/files/read', '/files/write'],
                'database'  => ['/database/status', '/database/tables', '/database/query'],
                'backup'    => ['/backup'],
                'diagnostics'=> ['/diagnostics'],
                'logs'      => ['/logs'],
            ],
            'authentication' => [
                'types'          => ['Application Password', 'API Token'],
                'required_for'   => 'mutating operations',
            ],
        ]);
    },
    'permission_callback' => '__return_true',
]);