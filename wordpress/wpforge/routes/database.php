<?php

namespace WPForge\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPForge\Core\Config;
use WPForge\Security\Validator;

/**
 * Database routes for WPForge API
 */
class DatabaseRoutes extends BaseRoutes
{
    private Config $config;
    private Validator $validator;

    public function __construct()
    {
        parent::__construct();
        $this->config = new Config();
        $this->validator = new Validator();
    }

    public static function register(): void
    {
        $instance = new self();

        register_rest_route($instance->namespace, '/database/status', [
            'methods' => 'GET',
            'callback' => [$instance, 'getStatus'],
            'permission_callback' => [$instance, 'checkPermission'],
        ]);

        register_rest_route($instance->namespace, '/database/tables', [
            'methods' => 'GET',
            'callback' => [$instance, 'getTables'],
            'permission_callback' => [$instance, 'checkPermission'],
        ]);

        register_rest_route($instance->namespace, '/database/table/(?P<table>[a-zA-Z0-9_]+)', [
            'methods' => 'GET',
            'callback' => [$instance, 'describeTable'],
            'permission_callback' => [$instance, 'checkPermission'],
        ]);

        register_rest_route($instance->namespace, '/database/query', [
            'methods' => 'POST',
            'callback' => [$instance, 'runQuery'],
            'permission_callback' => [$instance, 'checkQueryPermission'],
        ]);
    }

    public function getStatus(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        return $this->successResponse([
            'connected' => true,
            'database' => $wpdb->dbname,
            'host' => $wpdb->dbhost,
            'charset' => $wpdb->charset,
            'collate' => $wpdb->collate,
            'version' => $wpdb->db_version(),
            'prefix' => $wpdb->prefix,
        ]);
    }

    public function getTables(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}%'", ARRAY_N);
        
        $result = [];
        foreach ($tables as $table) {
            $table_name = $table[0];
            $result[] = [
                'name' => $table_name,
                'rows' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}"),
            ];
        }

        return $this->successResponse(['tables' => $result]);
    }

    public function describeTable(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $table = $request->get_param('table');
        
        // Validate table name - only allow WordPress tables
        if (!preg_match('/^' . preg_quote($wpdb->prefix, '/') . '[a-zA-Z0-9_]+$/', $table)) {
            return $this->errorResponse('invalid_table', 'Invalid table name.', 400);
        }

        $columns = $wpdb->get_results("DESCRIBE {$table}", ARRAY_A);

        return $this->successResponse([
            'table' => $table,
            'columns' => $columns,
        ]);
    }

    public function runQuery(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $query = trim($request->get_param('query'));
        $allow_writes = $request->get_param('allow_writes') ?? false;

        if (empty($query)) {
            return $this->errorResponse('missing_query', 'Query is required.', 400);
        }

        // Check if query is read-only
        if (!$this->validator->isReadOnlyQuery($query)) {
            if (!$allow_writes || !$this->config->allowDatabaseWrites()) {
                return $this->errorResponse(
                    'write_not_allowed', 
                    'Write queries are not allowed. Enable developer mode to allow writes.', 
                    403
                );
            }
        }

        // Execute query
        try {
            if ($this->validator->isReadOnlyQuery($query)) {
                $results = $wpdb->get_results($query, ARRAY_A);
                
                if ($wpdb->last_error) {
                    return $this->errorResponse('query_error', $wpdb->last_error, 400);
                }

                return $this->successResponse([
                    'results' => $results ?: [],
                    'count' => count($results ?: []),
                    'read_only' => true,
                ]);
            } else {
                // Write query
                $result = $wpdb->query($query);
                
                if ($wpdb->last_error) {
                    $this->logMutation('database_write', 'query', false, 400, 'query_error');
                    return $this->errorResponse('query_error', $wpdb->last_error, 400);
                }

                $this->logMutation('database_write', 'query', true, 200, null, ['query' => substr($query, 0, 100)]);

                return $this->successResponse([
                    'affected_rows' => $wpdb->rows_affected,
                    'insert_id' => $wpdb->insert_id,
                    'read_only' => false,
                ]);
            }
        } catch (\Exception $e) {
            return $this->errorResponse('query_failed', 'Query execution failed.', 500);
        }
    }

    public function checkPermission(): bool|WP_Error
    {
        $auth = $this->checkAuth();
        if (is_wp_error($auth)) {
            return $auth;
        }
        return $this->checkCapability('manage_options');
    }

    public function checkQueryPermission(): bool|WP_Error
    {
        return $this->checkPermission();
    }
}
