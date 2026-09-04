<?php

namespace WPForge\Diagnostics;

use WPForge\WordPress\WordPressService;
use WPForge\Elementor\ElementorService;
use WPForge\Core\Config;

/**
 * Diagnostics service for comprehensive site health checks
 */
class DiagnosticsService
{
    private WordPressService $wordpress;
    private ElementorService $elementor;
    private Config $config;

    public function __construct()
    {
        $this->wordpress = new WordPressService();
        $this->elementor = new ElementorService();
        $this->config = new Config();
    }

    /**
     * Run all diagnostics
     */
    public function run(): array
    {
        return [
            'timestamp' => current_time('mysql', true),
            'php' => $this->checkPhp(),
            'wordpress' => $this->checkWordPress(),
            'database' => $this->checkDatabase(),
            'filesystem' => $this->checkFilesystem(),
            'rest_api' => $this->checkRestApi(),
            'plugins' => $this->checkPlugins(),
            'themes' => $this->checkThemes(),
            'elementor' => $this->checkElementor(),
            'permissions' => $this->checkPermissions(),
            'performance' => $this->checkPerformance(),
            'cron' => $this->checkCron(),
            'security' => $this->checkSecurity(),
        ];
    }

    private function checkPhp(): array
    {
        return [
            'version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_vars' => ini_get('max_input_vars'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'extensions' => get_loaded_extensions(),
        ];
    }

    private function checkWordPress(): array
    {
        global $wp_version;
        
        return [
            'version' => $wp_version,
            'site_url' => get_site_url(),
            'home_url' => get_home_url(),
            'admin_url' => get_admin_url(),
            'environment_type' => $this->wordpress->getEnvironmentType(),
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG,
            'multisite' => is_multisite(),
            'permalink_structure' => get_option('permalink_structure'),
            'timezone' => get_option('timezone_string'),
            'language' => get_option('WPLANG'),
        ];
    }

    private function checkDatabase(): array
    {
        global $wpdb;
        
        return [
            'connected' => true,
            'host' => $wpdb->dbhost,
            'database' => $wpdb->dbname,
            'charset' => $wpdb->charset,
            'collate' => $wpdb->collate,
            'prefix' => $wpdb->prefix,
            'version' => $wpdb->db_version(),
            'table_count' => count($wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}%'")),
        ];
    }

    private function checkFilesystem(): array
    {
        $upload_dir = wp_upload_dir();
        
        return [
            'writable' => wp_is_writable($upload_dir['basedir']),
            'upload_dir' => $upload_dir['basedir'],
            'upload_url' => $upload_dir['baseurl'],
            'content_dir' => WP_CONTENT_DIR,
            'plugin_dir' => WPFORGE_PLUGIN_DIR,
            'method' => get_filesystem_method(),
        ];
    }

    private function checkRestApi(): array
    {
        $server = rest_get_server();
        
        return [
            'available' => true,
            'namespaces' => $server->get_namespaces(),
            'authentication_required' => true,
        ];
    }

    private function checkPlugins(): array
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $plugins = get_plugins();
        $active = get_option('active_plugins', []);
        
        return [
            'total' => count($plugins),
            'active_count' => count($active),
            'inactive_count' => count($plugins) - count($active),
        ];
    }

    private function checkThemes(): array
    {
        $themes = wp_get_themes();
        $active = wp_get_theme();
        
        return [
            'total' => count($themes),
            'active' => [
                'name' => $active->get('Name'),
                'version' => $active->get('Version'),
                'stylesheet' => $active->get_stylesheet(),
            ],
        ];
    }

    private function checkElementor(): array
    {
        $status = $this->elementor->getStatus();
        
        return [
            'installed' => $status['installed'],
            'active' => $status['active'],
            'version' => $status['version'],
            'pro_installed' => $status['pro_installed'],
            'pro_active' => $status['pro_active'],
            'pro_version' => $status['pro_version'],
        ];
    }

    private function checkPermissions(): array
    {
        $user = wp_get_current_user();
        
        return [
            'current_user_id' => $user->ID ?? 0,
            'current_roles' => $user->roles ?? [],
            'can_manage_options' => current_user_can('manage_options'),
            'can_edit_pages' => current_user_can('edit_pages'),
            'can_publish_pages' => current_user_can('publish_pages'),
            'can_upload_files' => current_user_can('upload_files'),
            'can_activate_plugins' => current_user_can('activate_plugins'),
            'can_install_plugins' => current_user_can('install_plugins'),
            'can_switch_themes' => current_user_can('switch_themes'),
            'can_edit_themes' => current_user_can('edit_themes'),
        ];
    }

    private function checkPerformance(): array
    {
        return [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'script_memory_limit' => ini_get('memory_limit'),
        ];
    }

    private function checkCron(): array
    {
        $crons = _get_cron_array();
        $disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        
        return [
            'enabled' => !$disabled,
            'event_count' => $crons ? count($crons) : 0,
            'disabled_by_constant' => $disabled,
        ];
    }

    private function checkSecurity(): array
    {
        return [
            'https' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'wpforge_enabled' => $this->config->get('enabled'),
            'wpforge_developer_mode' => $this->config->isDeveloperMode(),
            'wpforge_filesystem_writes' => $this->config->allowFilesystemWrites(),
            'wpforge_database_writes' => $this->config->allowDatabaseWrites(),
            'wpforge_audit_logging' => $this->config->auditLoggingEnabled(),
        ];
    }
}
