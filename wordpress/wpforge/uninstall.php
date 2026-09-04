<?php
/**
 * WPForge Uninstall Handler
 * 
 * This file runs when WPForge is deleted from WordPress.
 * It cleans up all plugin data including options, tables, and files.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if this is an uninstall request (not just deactivation)
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('wpforge_version');
delete_option('wpforge_config');

// Drop the logs table
global $wpdb;
$table_name = $wpdb->prefix . 'wpforge_logs';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Remove backup files
$upload_dir = wp_upload_dir();
$wpforge_dir = trailingslashit($upload_dir['basedir']) . 'wpforge';

if (is_dir($wpforge_dir)) {
    // Remove backups directory
    $backups_dir = trailingslashit($wpforge_dir) . 'backups';
    if (is_dir($backups_dir)) {
        $files = glob(trailingslashit($backups_dir) . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($backups_dir);
    }
    
    // Remove logs directory
    $logs_dir = trailingslashit($wpforge_dir) . 'logs';
    if (is_dir($logs_dir)) {
        $files = glob(trailingslashit($logs_dir) . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($logs_dir);
    }
    
    // Remove main wpforge dir if empty
    @rmdir($wpforge_dir);
}

// Clear any transients
delete_transient('wpforge_rate_limit_%');
