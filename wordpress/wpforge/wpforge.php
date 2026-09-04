<?php
/**
 * WPForge - AI-Powered WordPress Remote Control & Development Bridge
 *
 * @package           WPForge
 * @author            WPForge Team
 * @license           MIT
 * @version           0.1.0
 * @link              https://github.com/wpforge/wpforge
 *
 * @wordpress-plugin
 * Plugin Name:       WPForge
 * Plugin URI:        https://github.com/wpforge/wpforge
 * Description:       AI-Powered WordPress Remote Control & Development Bridge
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            WPForge Team
 * Author URI:        https://github.com/wpforge
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       wpforge
 * Domain Path:       /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WPFORGE_VERSION', '0.1.0');
define('WPFORGE_PLUGIN_FILE', __FILE__);
define('WPFORGE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPFORGE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPFORGE_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Autoloader for WPForge classes
 */
spl_autoload_register(function ($class) {
    // Only handle WPForge classes
    if (strpos($class, 'WPForge\\') !== 0) {
        return;
    }

    // Convert namespace to file path
    $relative_class = substr($class, strlen('WPForge\\'));
    $file_path = str_replace('\\', '/', $relative_class);

    // Try src directory first
    $src_file = WPFORGE_PLUGIN_DIR . 'src/' . $file_path . '.php';
    if (file_exists($src_file)) {
        require_once $src_file;
        return;
    }
});

/**
 * Main WPForge Plugin Class
 */
final class WPForge_Plugin
{
    private static ?self $instance = null;
    private array $services = [];

    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Prevent cloning
    }

    private function __clone() {}

    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton');
    }

    /**
     * Initialize the plugin
     */
    public function init(): void
    {
        // Check minimum requirements
        if (!$this->checkRequirements()) {
            add_action('admin_notices', [$this, 'requirementsNotice']);
            return;
        }

        // Load text domain
        add_action('plugins_loaded', [$this, 'loadTextDomain']);

        // Register REST routes
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Register activation/deactivation hooks
        register_activation_hook(WPFORGE_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(WPFORGE_PLUGIN_FILE, [$this, 'deactivate']);

        // Initialize services
        $this->initializeServices();
    }

    /**
     * Check minimum requirements
     */
    private function checkRequirements(): bool
    {
        global $wp_version;

        // Check WordPress version
        if (version_compare($wp_version, '6.0', '<')) {
            return false;
        }

        // Check PHP version
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            return false;
        }

        return true;
    }

    /**
     * Show requirements notice
     */
    public function requirementsNotice(): void
    {
        global $wp_version;
        ?>
        <div class="notice notice-error">
            <p>
                <strong>WPForge Error:</strong>
                <?php if (version_compare($wp_version, '6.0', '<')): ?>
                    WordPress 6.0 or higher is required. Current version: <?php echo esc_html($wp_version); ?>
                <?php elseif (version_compare(PHP_VERSION, '8.1', '<')): ?>
                    PHP 8.1 or higher is required. Current version: <?php echo esc_html(PHP_VERSION); ?>
                <?php else: ?>
                    Unknown requirement failure.
                <?php endif; ?>
            </p>
        </div>
        <?php
    }

    /**
     * Load plugin text domain
     */
    public function loadTextDomain(): void
    {
        load_plugin_textdomain('wpforge', false, dirname(WPFORGE_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * Initialize all services
     */
    private function initializeServices(): void
    {
        // Core services
        $this->services['logger'] = new \WPForge\Logging\Logger();
        $this->services['auth'] = new \WPForge\Auth\Authentication();
        $this->services['authorization'] = new \WPForge\Auth\Authorization();
        $this->services['security'] = new \WPForge\Security\Validator();
        $this->services['config'] = new \WPForge\Core\Config();

        // WordPress services
        $this->services['wordpress'] = new \WPForge\WordPress\WordPressService();
        $this->services['posts'] = new \WPForge\WordPress\PostsService();
        $this->services['pages'] = new \WPForge\WordPress\PagesService();
        $this->services['media'] = new \WPForge\Media\MediaService();
        $this->services['taxonomies'] = new \WPForge\WordPress\TaxonomiesService();
        $this->services['users'] = new \WPForge\WordPress\UsersService();
        $this->services['menus'] = new \WPForge\WordPress\MenusService();
        $this->services['themes'] = new \WPForge\Themes\ThemesService();
        $this->services['plugins'] = new \WPForge\Plugins\PluginsService();

        // Elementor service (conditionally)
        $this->services['elementor'] = new \WPForge\Elementor\ElementorService();

        // Filesystem service
        $this->services['filesystem'] = new \WPForge\Filesystem\FilesystemService();

        // Database service
        $this->services['database'] = new \WPForge\Database\DatabaseService();

        // Backup service
        $this->services['backup'] = new \WPForge\Backup\BackupService();

        // Diagnostics service
        $this->services['diagnostics'] = new \WPForge\Diagnostics\DiagnosticsService();

        // Cache service
        $this->services['cache'] = new \WPForge\WordPress\CacheService();
    }

    /**
     * Get a service by name
     */
    public function getService(string $name): mixed
    {
        return $this->services[$name] ?? null;
    }

    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        // Load route handlers
        require_once WPFORGE_PLUGIN_DIR . 'routes/system.php';
        require_once WPFORGE_PLUGIN_DIR . 'routes/site.php';
        require_once WPFORGE_PLUGIN_DIR . 'routes/posts.php';
        require_once WPFORGE_PLUGIN_DIR . 'routes/pages.php';
        require_once WPFORGE_PLUGIN_DIR . 'routes/media.php';
        require_once WPFORGE_PLUGIN_DIR . 'routes/taxonomies.php';
        require_once WPFORGE_PLUGIN_DIR . 'routes/users.php';
        require_once WPFORGE_PLUGIN_DIR . 'routes/menus.php';
        require_once WPFORGE_PLUGIN_DIR . 'routes/themes.php';
        require_once WPFORGE_PLUGIN_DIR . 'routes/plugins.php';
        require_once WPFORGE_PLUGIN_DIR . 'routes/elementor.php';
        require_once WPFORGE_PLUGIN_DIR . 'routes/filesystem.php';
        require_once WPFORGE_PLUGIN_DIR . 'routes/database.php';
        require_once WPFORGE_PLUGIN_DIR . 'routes/backup.php';
        require_once WPFORGE_PLUGIN_DIR . 'routes/diagnostics.php';
        require_once WPFORGE_PLUGIN_DIR . 'routes/logs.php';

        // Initialize all routes
        \WPForge\API\SystemRoutes::register();
        \WPForge\API\SiteRoutes::register();
        \WPForge\API\PostsRoutes::register();
        \WPForge\API\PagesRoutes::register();
        \WPForge\API\MediaRoutes::register();
        \WPForge\API\TaxonomiesRoutes::register();
        \WPForge\API\UsersRoutes::register();
        \WPForge\API\MenusRoutes::register();
        \WPForge\API\ThemesRoutes::register();
        \WPForge\API\PluginsRoutes::register();
        \WPForge\API\ElementorRoutes::register();
        \WPForge\API\FilesystemRoutes::register();
        \WPForge\API\DatabaseRoutes::register();
        \WPForge\API\BackupRoutes::register();
        \WPForge\API\DiagnosticsRoutes::register();
        \WPForge\API\LogsRoutes::register();
    }

    /**
     * Plugin activation
     */
    public function activate(): void
    {
        // Create necessary directories
        $upload_dir = wp_upload_dir();
        $wpforge_dir = trailingslashit($upload_dir['basedir']) . 'wpforge';
        $backups_dir = trailingslashit($wpforge_dir) . 'backups';
        $logs_dir = trailingslashit($wpforge_dir) . 'logs';

        wp_mkdir_p($wpforge_dir);
        wp_mkdir_p($backups_dir);
        wp_mkdir_p($logs_dir);

        // Set default options
        add_option('wpforge_version', WPFORGE_VERSION);
        add_option('wpforge_config', [
            'enabled' => true,
            'developer_mode' => false,
            'filesystem_root' => ABSPATH,
            'allow_filesystem_writes' => false,
            'allow_database_writes' => false,
            'allow_plugin_installation' => false,
            'allow_theme_activation' => false,
            'allow_destructive_operations' => false,
            'audit_logging' => true,
            'rate_limit_enabled' => true,
            'rate_limit_requests' => 100,
            'rate_limit_window' => 60,
        ]);

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

// Initialize the plugin
$GLOBALS['wpforge'] = WPForge_Plugin::instance();
$GLOBALS['wpforge']->init();
