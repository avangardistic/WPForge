<?php

namespace WPForge\Backup;

use WP_Error;
use WPForge\Core\Config;

/**
 * Backup service for creating and managing database backups
 */
class BackupService
{
    private Config $config;
    private string $backups_dir;

    public function __construct()
    {
        $this->config = new Config();
        
        $upload_dir = wp_upload_dir();
        $this->backups_dir = trailingslashit($upload_dir['basedir']) . 'wpforge/backups';
        
        // Ensure directory exists
        wp_mkdir_p($this->backups_dir);
    }

    /**
     * Create a backup
     */
    public function createBackup(string $scope = 'database', ?string $name = null): array|WP_Error
    {
        $backup_id = $this->generateBackupId();
        $timestamp = current_time('mysql', true);
        
        if ($scope === 'database') {
            return $this->createDatabaseBackup($backup_id, $name);
        }

        return new WP_Error('invalid_scope', 'Invalid backup scope.', ['status' => 400]);
    }

    /**
     * Create a database backup
     */
    private function createDatabaseBackup(string $backup_id, ?string $name): array|WP_Error
    {
        global $wpdb;

        $tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}%'", ARRAY_N);
        $sql = "-- WPForge Database Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n-- Site: " . get_site_url() . "\n\n";

        foreach ($tables as $table) {
            $table_name = $table[0];
            
            // Get CREATE TABLE statement
            $create = $wpdb->get_row("SHOW CREATE TABLE {$table_name}", ARRAY_A);
            $sql .= $create['Create Table'] . ";\n\n";

            // Get table data
            $rows = $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);
            
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $columns = array_keys($row);
                    $values = array_map(function ($val) use ($wpdb) {
                        return $val === null ? 'NULL' : "'" . $wpdb->_real_escape($val) . "'";
                    }, array_values($row));
                    
                    $sql .= "INSERT INTO {$table_name} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql .= "\n";
            }
        }

        // Write backup file
        $filename = $this->getBackupFilename($backup_id, 'database');
        $filepath = trailingslashit($this->backups_dir) . $filename;
        
        if (file_put_contents($filepath, $sql) === false) {
            return new WP_Error('backup_write_failed', 'Failed to write backup file.', ['status' => 500]);
        }

        // Store metadata
        $metadata = [
            'backup_id' => $backup_id,
            'name' => $name ?? 'Database Backup',
            'scope' => 'database',
            'created_at' => $timestamp,
            'size' => strlen($sql),
            'tables_count' => count($tables),
            'filename' => $filename,
        ];
        
        $this->saveMetadata($backup_id, $metadata);

        // Cleanup old backups
        $this->cleanupOldBackups();

        return [
            'success' => true,
            'backup_id' => $backup_id,
            'created_at' => $timestamp,
            'scope' => 'database',
            'size' => strlen($sql),
            'tables_count' => count($tables),
        ];
    }

    /**
     * List all backups
     */
    public function listBackups(): array
    {
        $files = glob(trailingslashit($this->backups_dir) . '*.meta.json');
        $backups = [];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $metadata = json_decode($content, true);
            
            if ($metadata && is_array($metadata)) {
                $backups[] = $metadata;
            }
        }

        // Sort by created_at descending
        usort($backups, function ($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });

        return $backups;
    }

    /**
     * Get a specific backup
     */
    public function getBackup(string $backup_id): ?array
    {
        $metadata_file = trailingslashit($this->backups_dir) . $backup_id . '.meta.json';
        
        if (!file_exists($metadata_file)) {
            return null;
        }

        $content = file_get_contents($metadata_file);
        return json_decode($content, true);
    }

    /**
     * Delete a backup
     */
    public function deleteBackup(string $backup_id): bool|WP_Error
    {
        $metadata = $this->getBackup($backup_id);
        
        if (!$metadata) {
            return new WP_Error('backup_not_found', 'Backup not found.', ['status' => 404]);
        }

        $backup_file = trailingslashit($this->backups_dir) . $metadata['filename'];
        $meta_file = trailingslashit($this->backups_dir) . $backup_id . '.meta.json';

        if (file_exists($backup_file)) {
            unlink($backup_file);
        }
        
        if (file_exists($meta_file)) {
            unlink($meta_file);
        }

        return true;
    }

    /**
     * Generate a unique backup ID
     */
    private function generateBackupId(): string
    {
        return 'bkp_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Get backup filename
     */
    private function getBackupFilename(string $backup_id, string $scope): string
    {
        $site_hash = substr(md5(get_site_url()), 0, 8);
        return "{$backup_id}_{$site_hash}_{$scope}.sql";
    }

    /**
     * Save backup metadata
     */
    private function saveMetadata(string $backup_id, array $metadata): void
    {
        $meta_file = trailingslashit($this->backups_dir) . $backup_id . '.meta.json';
        file_put_contents($meta_file, wp_json_encode($metadata, JSON_PRETTY_PRINT));
    }

    /**
     * Cleanup old backups based on retention policy
     */
    private function cleanupOldBackups(): void
    {
        $retention_days = $this->config->get('backup_retention_days', 7);
        $max_backups = 10;
        $cutoff = strtotime("-{$retention_days} days");

        $backups = $this->listBackups();
        $to_delete = [];

        foreach ($backups as $backup) {
            $created = strtotime($backup['created_at']);
            
            if ($created < $cutoff) {
                $to_delete[] = $backup['backup_id'];
            }
        }

        // Keep only max_backups most recent
        if (count($backups) > $max_backups) {
            $extra = array_slice($backups, $max_backups);
            foreach ($extra as $backup) {
                if (!in_array($backup['backup_id'], $to_delete)) {
                    $to_delete[] = $backup['backup_id'];
                }
            }
        }

        foreach ($to_delete as $backup_id) {
            $this->deleteBackup($backup_id);
        }
    }
}
