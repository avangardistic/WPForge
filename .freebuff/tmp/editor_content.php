<?php
/**
 * Plugin Name: WPForge
 * Plugin URI: https://github.com/wpforge/wpforge
 * Description: AI-Powered WordPress Remote Control & Development Bridge
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Requires WP: 6.0
 * Text Domain: wpforge
 * Domain Path: /languages
 * Network: false
 *
 * @package WPForge
 * @license MIT
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WPFORGE_VERSION', '1.0.0');
define('WPFORGE_PLUGIN_FILE', __FILE__);
define('WPFORGE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPFORGE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPFORGE_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WPFORGE_NAMESPACE', 'wpforge/v1');
define('WPFORGE_LOG_DIR', WP_CONTENT_DIR . '/wpforge-logs');
define('WPFORGE_BACKUP_DIR', WP_CONTENT_DIR . '/wpforge-backups');

spl_autoload_register(function ($class) {
    $prefix = 'WPForge\\';
    $base_dir = WPFORGE_PLUGIN_DIR . 'src/';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative_class = substr($class, strlen($prefix));
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

function wpforge_bootstrap(): void {
    $plugin = WPForge_Plugin::instance();
    $plugin->init();
}

final class WPForge_Plugin
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        register_activation_hook(WPFORGE_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(WPFORGE_PLUGIN_FILE, [$this, 'deactivate']);
    }

    public function registerRestRoutes(): void
    {
        $routeFiles = glob(WPFORGE_PLUGIN_DIR . 'routes/*.php');
        foreach ($routeFiles as $file) {
            require_once $file;
        }
    }

    public function activate(): void
    {
        $dirs = [WPFORGE_LOG_DIR, WPFORGE_BACKUP_DIR, WPFORGE_BACKUP_DIR . '/database', WPFORGE_BACKUP_DIR . '/files'];
        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
                $ht = $dir . '/.htaccess';
                if (!file_exists($ht)) {
                    file_put_contents($ht, "Deny from all\n");
                }
            }
        }

        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpforge_tokens (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            token_hash varchar(255) NOT NULL,
            token_id varchar(50) NOT NULL,
            user_id bigint(20) NOT NULL,
            description text,
            created_at datetime NOT NULL,
            last_used datetime DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            revoked tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY token_hash (token_hash),
            UNIQUE KEY token_id (token_id),
            KEY user_id (user_id)
        ) {$charset};");

        dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpforge_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            request_id varchar(64) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            username varchar(60) DEFAULT '',
            operation varchar(100) NOT NULL,
            target varchar(500) NOT NULL,
            success tinyint(1) NOT NULL,
            http_status int(3) NOT NULL,
            error_code varchar(100) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent varchar(500) DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            timestamp datetime NOT NULL,
            PRIMARY KEY (id),
            KEY request_id (request_id),
            KEY user_id (user_id),
            KEY operation (operation),
            KEY timestamp (timestamp)
        ) {$charset};");

        add_option('wpforge_version', WPFORGE_VERSION);
        add_option('wpforge_enabled', true);
        flush_rewrite_rules();
    }

    public function deactivate(): void
    {
        flush_rewrite_rules();
    }
}

add_action('plugins_loaded', 'wpforge_bootstrap');
