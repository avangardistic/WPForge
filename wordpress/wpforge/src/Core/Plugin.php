<?php

namespace WPForge\Core;

/**
 * Core Plugin singleton — orchestrates initialisation, hooks, and DB schema.
 */
class Plugin
{
    private static ?self $instance = null;
    private Container $container;
    private bool $initialized = false;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->container = new Container();
    }

    private function __clone()
    {
    }

    public function __wakeup()
    {
        throw new \RuntimeException('Cannot unserialize singleton');
    }

    public function init(): void
    {
        if ($this->initialized) {
            return;
        }

        try {
            $this->loadConfig();
            $this->initLogging();
            $this->initMiddleware();
            $this->registerHooks();
            $this->initialized = true;

            $logger = $this->container->get('logger');
            if ($logger) {
                $logger->logSuccess('plugin_init', 'wpforge');
            }
        } catch (\Throwable $e) {
            $this->handleInitError($e);
        }
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    /* ------------------------------------------------------------------ */
    /*  Private bootstrap helpers                                          */
    /* ------------------------------------------------------------------ */

    private function loadConfig(): void
    {
        $config = new Config();
        $this->container->set('config', $config);
    }

    private function initLogging(): void
    {
        $logger = new \WPForge\Logging\Logger();
        $logger->createTable();
        $this->container->set('logger', $logger);
    }

    private function initMiddleware(): void
    {
        $config = $this->container->get('config');
        $this->container->set('rate_limit', new \WPForge\API\Middleware\RateLimit($config));
    }

    private function registerHooks(): void
    {
        register_activation_hook(WPFORGE_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(WPFORGE_PLUGIN_FILE, [$this, 'deactivate']);

        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_filter('rest_authentication_errors', [$this, 'authenticateRestRequest']);
        add_filter('rest_pre_dispatch', [$this, 'enforceRateLimit'], 10, 3);
        add_filter('rest_request_after_callbacks', [$this, 'auditLogMutation'], 10, 3);

        // Register the wp-admin UI right away (at plugins_loaded) instead of
        // deferring to admin_init: WP fires the 'admin_menu' action while it
        // loads wp-admin/menu.php, which happens BEFORE admin_init, so any
        // admin_menu listener added during admin_init would never run.
        if (is_admin()) {
            $admin = new \WPForge\Admin\AdminUI();
            $admin->register();
        }
    }

    /**
     * Global REST authentication filter — enables WPForge Bearer-token auth
     * in addition to WordPress cookies / Application Passwords.
     */
    public function authenticateRestRequest(\WP_Error|bool|null $result): \WP_Error|bool|null
    {
        // If an earlier check already failed authentication, respect it.
        if (is_wp_error($result)) {
            return $result;
        }

        // If the request is already authenticated, leave it alone.
        if (is_user_logged_in()) {
            return $result;
        }

        $token = $this->extractBearerToken();
        if ($token === null) {
            return $result; // No Bearer token — let route permission callbacks decide.
        }

        $authenticator = new \WPForge\Auth\Authenticator();
        $user = $authenticator->authenticateBearerTokenString($token);

        if ($user instanceof \WP_User) {
            wp_set_current_user($user->ID);
            return $result;
        }

        return new \WP_Error('wpforge_invalid_token', 'Invalid or expired WPForge API token.', ['status' => 401]);
    }

    /**
     * Global REST dispatch filter — enforces per-IP rate limiting on WPForge routes.
     */
    public function enforceRateLimit(mixed $result, \WP_REST_Server $server, \WP_REST_Request $request): mixed
    {
        if ($result !== null) {
            return $result;
        }

        $route = $request->get_route();
        $prefix = '/' . WPFORGE_NAMESPACE;
        if ($route !== $prefix && strpos($route, $prefix . '/') !== 0) {
            return $result;
        }

        $rateLimit = $this->container->get('rate_limit');
        if (!$rateLimit instanceof \WPForge\API\Middleware\RateLimit) {
            $config = $this->container->get('config') ?: new Config();
            $rateLimit = new \WPForge\API\Middleware\RateLimit($config);
            $this->container->set('rate_limit', $rateLimit);
        }

        $check = $rateLimit($request);
        if ($check instanceof \WP_Error) {
            $data = $check->get_error_data();
            $status = is_array($data) && isset($data['status']) ? (int) $data['status'] : 429;
            return \WPForge\API\Response::error('RATE_LIMITED', $check->get_error_message(), $status);
        }

        return $result;
    }

    /**
     * Global REST dispatch filter — records mutating WPForge API calls to wpforge_logs.
     */
    public function auditLogMutation(mixed $response, array $handler, \WP_REST_Request $request): mixed
    {
        $route = $request->get_route();
        $prefix = '/' . WPFORGE_NAMESPACE;
        if ($route !== $prefix && strpos($route, $prefix . '/') !== 0) {
            return $response;
        }

        $method = strtoupper($request->get_method());
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $response;
        }

        $logger = $this->container->get('logger');
        if (!$logger instanceof \WPForge\Logging\Logger) {
            $logger = new \WPForge\Logging\Logger();
            $this->container->set('logger', $logger);
        }

        $body = $request->get_json_params();
        if (!is_array($body) || empty($body)) {
            $body = $request->get_body_params();
        }
        if (!is_array($body)) {
            $body = [];
        }

        $rawBody = $request->get_body();
        $bodyJson = wp_json_encode($body);
        if ($bodyJson === false || $bodyJson === null) {
            $bodyJson = '';
        }
        $bodySize = strlen($rawBody !== '' ? $rawBody : (string) $bodyJson);
        if ($bodySize > 65536) {
            $body = sprintf('[TRUNCATED] %d bytes', $bodySize);
        }

        $status = 200;
        $errorCode = null;
        if ($response instanceof \WP_HTTP_Response) {
            $status = (int) $response->get_status();
            if ($status < 200 || $status >= 400) {
                $data = $response->get_data();
                if (is_array($data)) {
                    if (isset($data['error']['code']) && is_string($data['error']['code'])) {
                        $errorCode = $data['error']['code'];
                    } elseif (isset($data['code']) && is_string($data['code'])) {
                        $errorCode = $data['code'];
                    }
                }
            }
        } elseif (is_wp_error($response)) {
            $errData = $response->get_error_data();
            $status = is_array($errData) && isset($errData['status']) ? (int) $errData['status'] : 500;
            $errorCode = (string) $response->get_error_code();
        }
        $success = $status >= 200 && $status < 400;

        $logger->log(
            $method . ' ' . $route,
            $route,
            $success,
            $status,
            $errorCode,
            [
                'method'     => $method,
                'route'      => $route,
                'request_id' => RequestID::get(),
                'body'       => $body,
            ]
        );

        return $response;
    }

    /**
     * Extract the Bearer token from the current request.
     */
    private function extractBearerToken(): ?string
    {
        // Sanitising would corrupt the token; the value is parsed and verified below.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- see note above.
        $header = isset($_SERVER['HTTP_AUTHORIZATION']) ? wp_unslash($_SERVER['HTTP_AUTHORIZATION']) : '';

        // Apache may strip the header into REDIRECT_HTTP_AUTHORIZATION.
        if ($header === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- see note above.
            $header = wp_unslash($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }

        if ($header === '' && function_exists('getallheaders')) {
            foreach ((array) getallheaders() as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0) {
                    $header = wp_unslash($value);
                    break;
                }
            }
        }

        if ($header === '' || stripos($header, 'Bearer ') !== 0) {
            return null;
        }

        $token = trim(substr($header, 7));
        return $token !== '' ? $token : null;
    }

    /* ------------------------------------------------------------------ */
    /*  REST route registration                                            */
    /* ------------------------------------------------------------------ */

    public function registerRestRoutes(): void
    {
        $routeFiles = glob(WPFORGE_PLUGIN_DIR . 'routes/*.php');
        foreach ($routeFiles as $file) {
            require_once $file;
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Activation / Deactivation                                          */
    /* ------------------------------------------------------------------ */

    public function activate(): void
    {
        $this->createDirectories();
        $this->initTables();

        add_option('wpforge_version', WPFORGE_VERSION);
        add_option('wpforge_enabled', true);
        add_option('wpforge_developer_mode', false);

        flush_rewrite_rules();
    }

    public function deactivate(): void
    {
        flush_rewrite_rules();
    }

    /* ------------------------------------------------------------------ */
    /*  Directory helpers                                                  */
    /* ------------------------------------------------------------------ */

    private function createDirectories(): void
    {
        $dirs = [
            WPFORGE_LOG_DIR,
            WPFORGE_BACKUP_DIR,
            WPFORGE_BACKUP_DIR . '/database',
            WPFORGE_BACKUP_DIR . '/files',
        ];

        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
                $htaccess = $dir . '/.htaccess';
                if (!file_exists($htaccess)) {
                    file_put_contents($htaccess, "Deny from all\n");
                }
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Database schema                                                    */
    /* ------------------------------------------------------------------ */

    private function initTables(): void
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        // Tokens table
        $tokensTable = $wpdb->prefix . 'wpforge_tokens';
        $sql = "CREATE TABLE IF NOT EXISTS {$tokensTable} (
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
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Logs table
        $logsTable = $wpdb->prefix . 'wpforge_logs';
        $sql = "CREATE TABLE IF NOT EXISTS {$logsTable} (
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
        ) {$charsetCollate};";

        dbDelta($sql);
    }

    private function handleInitError(\Throwable $e): void
    {
        error_log('WPForge initialization failed: ' . $e->getMessage());
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($e->getTraceAsString());
        }
    }
}
