<?php

namespace WPForge\Database;

use WP_Error;

/**
 * Database inspection service
 */
class DatabaseService
{
    public function getStatus(): array
    {
        global $wpdb;
        
        return [
            'connected' => true,
            'database' => $wpdb->dbname,
            'host' => $wpdb->dbhost,
            'charset' => $wpdb->charset,
            'prefix' => $wpdb->prefix,
        ];
    }
    
    public function getTables(): array
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
        
        return $result;
    }
    
    public function describeTable(string $table): array|WP_Error
    {
        global $wpdb;
        
        // Validate table name
        if (!preg_match('/^' . preg_quote($wpdb->prefix, '/') . '[a-zA-Z0-9_]+$/', $table)) {
            return new WP_Error('invalid_table', 'Invalid table name.');
        }
        
        return $wpdb->get_results("DESCRIBE {$table}", ARRAY_A);
    }
    
    public function runSelectQuery(string $query): array|WP_Error
    {
        global $wpdb;
        
        // Only allow SELECT queries
        $query = trim($query);
        if (!preg_match('/^(SELECT|SHOW|DESCRIBE|EXPLAIN)\s/i', $query)) {
            return new WP_Error('query_not_allowed', 'Only read-only queries are allowed.');
        }
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if ($wpdb->last_error) {
            return new WP_Error('query_error', $wpdb->last_error);
        }
        
        return $results ?: [];
    }
}
