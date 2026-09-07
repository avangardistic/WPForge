<?php

namespace WPForge\Diagnostics;

/**
 * System diagnostics checker.
 */
class SystemChecker
{
    /**
     * Check PHP environment.
     */
    public function checkPHP(): array
    {
        return [
            'version'          => PHP_VERSION,
            'sapi'             => php_sapi_name(),
            'memory_limit'     => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'post_max_size'    => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'extensions'       => $this->getExtensions(),
            'is_cli'           => php_sapi_name() === 'cli',
        ];
    }

    /**
     * Check WordPress environment.
     */
    public function checkWordPress(): array
    {
        return [
            'version'      => get_bloginfo('version'),
            'site_url'     => get_site_url(),
            'home_url'     => get_home_url(),
            'environment'  => wp_get_environment_type(),
            'debug'        => defined('WP_DEBUG') && WP_DEBUG,
            'multisite'    => is_multisite(),
            'language'     => get_bloginfo('language'),
            'timezone'     => get_option('timezone_string'),
            'rewrite_rules' => (bool) get_option('rewrite_rules'),
        ];
    }

    /**
     * Check server environment.
     */
    /**
     * Read a $_SERVER value, unslashed and sanitised.
     *
     * These end up in API responses, so they are treated as untrusted input
     * even though they originate from the web server.
     */
    private static function server(string $key): ?string
    {
        if (!isset($_SERVER[$key])) {
            return null;
        }

        return sanitize_text_field(wp_unslash($_SERVER[$key]));
    }

    public function checkServer(): array
    {
        return [
            'software'   => self::server('SERVER_SOFTWARE') ?? 'unknown',
            'https'      => !empty($_SERVER['HTTPS']) && self::server('HTTPS') !== 'off',
            'port'       => self::server('SERVER_PORT'),
            'protocol'   => self::server('SERVER_PROTOCOL'),
            'remote_addr' => self::server('REMOTE_ADDR'),
            'disk_free'  => function_exists('disk_free_space') ? @disk_free_space(ABSPATH) : null,
        ];
    }

    /**
     * Check database connectivity.
     */
    public function checkDatabase(): array
    {
        global $wpdb;

        $start = microtime(true);
        $connected = $wpdb->check_connection(false);
        $latency = round((microtime(true) - $start) * 1000, 2);

        return [
            'connected' => $connected,
            'version'   => $wpdb->db_version(),
            'charset'   => $wpdb->charset,
            'latency_ms' => $latency,
            'prefix'    => $wpdb->prefix,
        ];
    }

    /**
     * Check filesystem permissions.
     */
    public function checkFilesystem(): array
    {
        $checks = [];

        $dirs = [
            'wp_content'  => WP_CONTENT_DIR,
            'wp_uploads'  => wp_upload_dir()['basedir'],
            'wp_plugins'  => WP_PLUGIN_DIR,
            'plugin_dir'  => WPFORGE_PLUGIN_DIR,
        ];

        foreach ($dirs as $key => $dir) {
            $checks[$key] = [
                'path'      => $dir,
                'exists'    => is_dir($dir),
                'writable'  => is_writable($dir),
                'readable'  => is_readable($dir),
            ];
        }

        return $checks;
    }

    /**
     * Run all system checks.
     */
    public function checkAll(): array
    {
        return [
            'php'        => $this->checkPHP(),
            'wordpress'  => $this->checkWordPress(),
            'server'     => $this->checkServer(),
            'database'   => $this->checkDatabase(),
            'filesystem' => $this->checkFilesystem(),
            'timestamp'  => current_time('mysql'),
        ];
    }

    private function getExtensions(): array
    {
        $required = ['json', 'mbstring', 'curl', 'openssl', 'zip', 'zlib'];
        $result = [];

        foreach ($required as $ext) {
            $result[$ext] = extension_loaded($ext);
        }

        return $result;
    }
}
