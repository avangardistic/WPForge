<?php

namespace WPForge\Logging;

use WPForge\Core\Config;

/**
 * Audit logging service for WPForge
 */
class Logger
{
    private const LOG_TABLE_SUFFIX = '_wpforge_logs';
    private const MAX_LOG_ENTRIES = 10000;

    private ?Config $config = null;

    /**
     * Get configuration instance
     */
    private function getConfig(): Config
    {
        if (null === $this->config) {
            $this->config = new Config();
        }
        return $this->config;
    }

    /**
     * Log an action
     *
     * @param string $operation The operation performed
     * @param string $target The target of the operation
     * @param bool $success Whether the operation succeeded
     * @param int $httpStatus HTTP status code
     * @param string|null $errorCode Error code if failed
     * @param array $metadata Additional metadata
     * @return int|false Log entry ID or false on failure
     */
    public function log(
        string $operation,
        string $target,
        bool $success = true,
        int $httpStatus = 200,
        ?string $errorCode = null,
        array $metadata = []
    ): int|false {
        // Check if logging is enabled
        if (!$this->getConfig()->auditLoggingEnabled()) {
            return false;
        }

        global $wpdb;

        $table_name = $wpdb->prefix . self::LOG_TABLE_SUFFIX;

        // Get current user
        $user_id = get_current_user_id();
        $user = $user_id ? wp_get_current_user() : null;

        // Generate request ID if not provided
        $request_id = $metadata['request_id'] ?? $this->generateRequestId();

        // Sanitize metadata - remove sensitive data
        $metadata = $this->sanitizeMetadata($metadata);

        $data = [
            'timestamp' => current_time('mysql', true),
            'request_id' => $request_id,
            'user_id' => $user_id ?: 0,
            'username' => $user ? $user->user_login : 'anonymous',
            'operation' => sanitize_text_field($operation),
            'target' => sanitize_text_field($target),
            'success' => $success ? 1 : 0,
            'http_status' => $httpStatus,
            'error_code' => $errorCode ? sanitize_text_field($errorCode) : null,
            'ip_address' => $this->getClientIp(),
            'user_agent' => $this->getUserAgent(),
            'metadata' => !empty($metadata) ? wp_json_encode($metadata) : null,
        ];

        $result = $wpdb->insert($table_name, $data);

        if ($result) {
            $log_id = $wpdb->insert_id;

            // Cleanup old logs if needed
            $this->cleanupOldLogs();

            return $log_id;
        }

        return false;
    }

    /**
     * Log a successful action
     */
    public function logSuccess(
        string $operation,
        string $target,
        int $httpStatus = 200,
        array $metadata = []
    ): int|false {
        return $this->log($operation, $target, true, $httpStatus, null, $metadata);
    }

    /**
     * Log a failed action
     */
    public function logError(
        string $operation,
        string $target,
        string $errorCode,
        int $httpStatus = 400,
        array $metadata = []
    ): int|false {
        return $this->log($operation, $target, false, $httpStatus, $errorCode, $metadata);
    }

    /**
     * Get logs with pagination and filtering
     */
    public function getLogs(array $args = []): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::LOG_TABLE_SUFFIX;

        $defaults = [
            'page' => 1,
            'per_page' => 50,
            'user_id' => null,
            'operation' => null,
            'success' => null,
            'date_from' => null,
            'date_to' => null,
            'search' => null,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $params = [];

        if ($args['user_id']) {
            $where[] = 'user_id = %d';
            $params[] = $args['user_id'];
        }

        if ($args['operation']) {
            $where[] = 'operation = %s';
            $params[] = $args['operation'];
        }

        if (null !== $args['success']) {
            $where[] = 'success = %d';
            $params[] = $args['success'] ? 1 : 0;
        }

        if ($args['date_from']) {
            $where[] = 'timestamp >= %s';
            $params[] = $args['date_from'];
        }

        if ($args['date_to']) {
            $where[] = 'timestamp <= %s';
            $params[] = $args['date_to'];
        }

        if ($args['search']) {
            $where[] = '(target LIKE %s OR username LIKE %s OR operation LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }

        $offset = ($args['page'] - 1) * $args['per_page'];

        $sql = "SELECT * FROM {$table_name} WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY timestamp DESC LIMIT %d OFFSET %d";
        $params[] = $args['per_page'];
        $params[] = $offset;

        $results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM {$table_name} WHERE " . implode(' AND ', $where);
        $total = $wpdb->get_var($wpdb->prepare($count_sql, array_slice($params, 0, count($params) - 2)));

        return [
            'logs' => $results ?: [],
            'total' => (int) $total,
            'page' => $args['page'],
            'per_page' => $args['per_page'],
            'total_pages' => ceil($total / $args['per_page']),
        ];
    }

    /**
     * Get a single log entry by ID
     */
    public function getLog(int $log_id): ?array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::LOG_TABLE_SUFFIX;

        $log = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $log_id),
            ARRAY_A
        );

        if ($log && !empty($log['metadata'])) {
            $log['metadata'] = json_decode($log['metadata'], true);
        }

        return $log ?: null;
    }

    /**
     * Delete old log entries
     */
    public function cleanupOldLogs(?int $days = null): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::LOG_TABLE_SUFFIX;

        // First, check if we have too many entries
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

        if ($total <= self::MAX_LOG_ENTRIES) {
            return;
        }

        // Delete oldest entries to get back to max
        $to_delete = $total - self::MAX_LOG_ENTRIES;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} ORDER BY timestamp ASC LIMIT %d",
                $to_delete
            )
        );
    }

    /**
     * Clear all logs
     */
    public function clearAll(): bool
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::LOG_TABLE_SUFFIX;

        return (bool) $wpdb->query("TRUNCATE TABLE {$table_name}");
    }

    /**
     * Create the logs table
     */
    public function createTable(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::LOG_TABLE_SUFFIX;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            timestamp datetime NOT NULL,
            request_id varchar(64) NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            username varchar(60) NOT NULL DEFAULT '',
            operation varchar(100) NOT NULL,
            target varchar(500) NOT NULL,
            success tinyint(1) NOT NULL DEFAULT 1,
            http_status int(3) NOT NULL DEFAULT 200,
            error_code varchar(100) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent varchar(500) DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            PRIMARY KEY (id),
            KEY timestamp (timestamp),
            KEY request_id (request_id),
            KEY user_id (user_id),
            KEY operation (operation)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Drop the logs table
     */
    public function dropTable(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::LOG_TABLE_SUFFIX;
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }

    /**
     * Generate a unique request ID
     */
    private function generateRequestId(): string
    {
        return sprintf(
            '%s-%s',
            date('YmdHis'),
            bin2hex(random_bytes(8))
        );
    }

    /**
     * Sanitize metadata to remove sensitive information
     */
    private function sanitizeMetadata(array $metadata): array
    {
        $sensitive_keys = [
            'password',
            'secret',
            'token',
            'api_key',
            'apikey',
            'authorization',
            'cookie',
            'credential',
            'private_key',
        ];

        foreach ($sensitive_keys as $key) {
            unset($metadata[$key]);

            // Also check for partial matches
            foreach (array_keys($metadata) as $meta_key) {
                if (stripos($meta_key, $key) !== false) {
                    unset($metadata[$meta_key]);
                }
            }
        }

        return $metadata;
    }

    /**
     * Get client IP address
     */
    private function getClientIp(): ?string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }
        return null;
    }

    /**
     * Get user agent
     */
    private function getUserAgent(): ?string
    {
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            return sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']));
        }
        return null;
    }
}
