<?php

namespace WPForge\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPForge\Core\Config;
use WPForge\WordPress\WordPressService;

/**
 * System routes for WPForge API
 */
class SystemRoutes extends BaseRoutes
{
    private Config $config;
    private WordPressService $wordpress;

    public function __construct()
    {
        parent::__construct();
        $this->config = new Config();
        $this->wordpress = new WordPressService();
    }

    public static function register(): void
    {
        $instance = new self();

        // GET /status - API status
        register_rest_route($instance->namespace, '/status', [
            'methods' => 'GET',
            'callback' => [$instance, 'getStatus'],
            'permission_callback' => '__return_true',
        ]);

        // GET /capabilities - User capabilities
        register_rest_route($instance->namespace, '/capabilities', [
            'methods' => 'GET',
            'callback' => [$instance, 'getCapabilities'],
            'permission_callback' => [$instance, 'checkAuth'],
        ]);

        // GET /environment - Environment information
        register_rest_route($instance->namespace, '/environment', [
            'methods' => 'GET',
            'callback' => [$instance, 'getEnvironment'],
            'permission_callback' => [$instance, 'checkAuth'],
        ]);

        // GET /health - Health check
        register_rest_route($instance->namespace, '/health', [
            'methods' => 'GET',
            'callback' => [$instance, 'getHealth'],
            'permission_callback' => '__return_true',
        ]);

        // GET / - API manifest
        register_rest_route($instance->namespace, '/', [
            'methods' => 'GET',
            'callback' => [$instance, 'getManifest'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Get API status
     */
    public function getStatus(): WP_REST_Response
    {
        return $this->successResponse([
            'name' => 'WPForge',
            'version' => WPFORGE_VERSION,
            'api_version' => 'v1',
            'namespace' => $this->namespace,
            'enabled' => $this->config->get('enabled'),
            'developer_mode' => $this->config->isDeveloperMode(),
            'wordpress_version' => $this->wordpress->getVersion(),
            'php_version' => PHP_VERSION,
            'timestamp' => current_time('mysql', true),
        ]);
    }

    /**
     * Get user capabilities
     */
    public function getCapabilities(): WP_REST_Response
    {
        $user = wp_get_current_user();
        
        if (!$user || !$user->exists()) {
            return $this->errorResponse('user_not_found', 'Current user not found.', 404);
        }

        $all_caps = array_keys($user->allcaps);
        $relevant_caps = [];
        
        $capability_descriptions = [
            'manage_options' => 'Access site settings',
            'edit_pages' => 'Edit pages',
            'publish_pages' => 'Publish pages',
            'edit_others_pages' => 'Edit others\' pages',
            'delete_pages' => 'Delete pages',
            'delete_others_pages' => 'Delete others\' pages',
            'edit_posts' => 'Edit posts',
            'publish_posts' => 'Publish posts',
            'edit_others_posts' => 'Edit others\' posts',
            'delete_posts' => 'Delete posts',
            'delete_others_posts' => 'Delete others\' posts',
            'upload_files' => 'Upload files',
            'switch_themes' => 'Switch themes',
            'edit_themes' => 'Edit theme files',
            'activate_plugins' => 'Activate plugins',
            'install_plugins' => 'Install plugins',
            'update_plugins' => 'Update plugins',
            'delete_plugins' => 'Delete plugins',
            'edit_users' => 'Edit users',
            'promote_users' => 'Promote users',
            'delete_users' => 'Delete users',
            'create_users' => 'Create users',
            'list_users' => 'List users',
            'unfiltered_html' => 'Post unfiltered HTML',
            'unfiltered_upload' => 'Upload any file type',
        ];

        foreach ($all_caps as $cap) {
            if (isset($capability_descriptions[$cap])) {
                $relevant_caps[$cap] = $capability_descriptions[$cap];
            }
        }

        return $this->successResponse([
            'user_id' => $user->ID,
            'username' => $user->user_login,
            'roles' => $user->roles,
            'capabilities' => $relevant_caps,
            'is_administrator' => in_array('administrator', $user->roles, true),
            'is_super_admin' => is_super_admin($user->ID),
        ]);
    }

    /**
     * Get environment information
     */
    public function getEnvironment(): WP_REST_Response
    {
        global $wpdb;

        return $this->successResponse([
            'wordpress' => [
                'version' => $this->wordpress->getVersion(),
                'site_url' => $this->wordpress->getSiteUrl(),
                'home_url' => $this->wordpress->getHomeUrl(),
                'admin_url' => $this->wordpress->getAdminUrl(),
                'environment_type' => $this->wordpress->getEnvironmentType(),
                'debug_mode' => $this->wordpress->isDebugMode(),
                'multisite' => $this->wordpress->isMultisite(),
                'permalink_structure' => $this->wordpress->getPermalinkStructure(),
            ],
            'php' => [
                'version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'max_input_vars' => ini_get('max_input_vars'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
            ],
            'database' => [
                'version' => $wpdb->db_version(),
                'charset' => $wpdb->charset,
                'collate' => $wpdb->collate,
            ],
            'server' => [
                'software' => $_SERVER['SERVER_SOFTWARE'] ?? null,
                'https' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'port' => $_SERVER['SERVER_PORT'] ?? null,
            ],
            'wpforge' => [
                'version' => WPFORGE_VERSION,
                'plugin_dir' => WPFORGE_PLUGIN_DIR,
                'filesystem_root' => $this->config->getFilesystemRoot(),
                'allow_filesystem_writes' => $this->config->allowFilesystemWrites(),
                'allow_database_writes' => $this->config->allowDatabaseWrites(),
                'audit_logging' => $this->config->auditLoggingEnabled(),
                'rate_limit_enabled' => $this->config->rateLimitEnabled(),
            ],
        ]);
    }

    /**
     * Health check endpoint
     */
    public function getHealth(): WP_REST_Response
    {
        $checks = [
            'wordpress_loaded' => function_exists('is_user_logged_in'),
            'rest_api_available' => class_exists('WP_REST_Server'),
            'database_connected' => !empty($GLOBALS['wpdb']),
            'filesystem_writable' => is_writable(wp_upload_dir()['basedir']),
        ];

        $healthy = !in_array(false, $checks, true);

        return $this->successResponse([
            'healthy' => $healthy,
            'checks' => $checks,
            'timestamp' => current_time('mysql', true),
        ]);
    }

    /**
     * Get API manifest
     */
    public function getManifest(): WP_REST_Response
    {
        $rest_server = rest_get_server();
        $namespaces = $rest_server->get_namespaces();
        
        $wpforge_routes = [];
        foreach ($namespaces as $ns) {
            if (strpos($ns, 'wpforge') !== false) {
                $routes = $rest_server->get_routes($ns);
                $wpforge_routes[$ns] = array_keys($routes);
            }
        }

        return $this->successResponse([
            'name' => 'WPForge',
            'version' => WPFORGE_VERSION,
            'api_version' => 'v1',
            'namespace' => $this->namespace,
            'description' => 'AI-Powered WordPress Remote Control & Development Bridge',
            'capabilities' => [
                'site_inspection' => true,
                'content_management' => true,
                'media_handling' => true,
                'elementor_integration' => class_exists('\Elementor\Plugin'),
                'filesystem_access' => true,
                'database_inspection' => true,
                'plugin_management' => true,
                'theme_management' => true,
                'backup_creation' => true,
                'audit_logging' => $this->config->auditLoggingEnabled(),
            ],
            'routes' => $wpforge_routes,
            'authentication' => [
                'required_for_mutations' => true,
                'supported_methods' => ['application_passwords', 'cookie'],
            ],
        ]);
    }
}
