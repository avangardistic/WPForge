<?php
/**
 * WPForge uninstall — clean up all data on plugin deletion.
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Drop tables
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wpforge_tokens");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wpforge_logs");

// Delete options
delete_option('wpforge_version');
delete_option('wpforge_enabled');
delete_option('wpforge_config');
delete_option('wpforge_developer_mode');

// Remove log and backup directories
$logDir = WP_CONTENT_DIR . '/wpforge-logs';
$backupDir = WP_CONTENT_DIR . '/wpforge-backups';

function wpforge_recursive_delete($dir) {
    if (!is_dir($dir)) {
        return;
    }
    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        $path = $dir . '/' . $item;
        is_dir($path) ? wpforge_recursive_delete($path) : unlink($path);
    }
    rmdir($dir);
}

if (is_dir($logDir)) {
    wpforge_recursive_delete($logDir);
}
if (is_dir($backupDir)) {
    wpforge_recursive_delete($backupDir);
}
