<?php
namespace WPForge\Backup;

/**
 * Database backup — export all tables to SQL.
 */
class DatabaseBackup
{
    private string $backupDir;

    public function __construct(string $backupDir)
    {
        $this->backupDir = $backupDir;
    }

    /**
     * Create a database backup.
     */
    public function create(array $options = []): array
    {
        global $wpdb;

        $backupId = Manager::generateBackupId('database');
        $filename = $backupId . '.sql';
        $filePath = $this->backupDir . $filename;

        $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
        $output = "# WPForge Database Backup\n";
        $output .= "# Date: " . current_time('mysql') . "\n";
        $output .= "# Backup ID: {$backupId}\n\n";

        foreach ($tables as $table) {
            $tableName = $table[0];

            $output .= "DROP TABLE IF EXISTS `{$tableName}`;\n";

            $createTable = $wpdb->get_row("SHOW CREATE TABLE `{$tableName}`", ARRAY_N);
            $output .= $createTable[1] . ";\n\n";

            $rows = $wpdb->get_results("SELECT * FROM `{$tableName}`", ARRAY_A);
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $columnsSql = '`' . implode('`, `', $columns) . '`';

                foreach ($rows as $row) {
                    $values = [];
                    foreach ($row as $value) {
                        if (is_null($value)) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . addslashes($value) . "'";
                        }
                    }
                    $output .= "INSERT INTO `{$tableName}` ({$columnsSql}) VALUES (" . implode(', ', $values) . ");\n";
                }
                $output .= "\n";
            }
        }

        // Write plain SQL.
        file_put_contents($filePath, $output);

        // Compress if gzopen available.
        $finalFile = $filePath;
        $finalName = $filename;

        if (function_exists('gzopen')) {
            $gzPath = $filePath . '.gz';
            $gz = gzopen($gzPath, 'wb9');
            if ($gz) {
                gzwrite($gz, $output);
                gzclose($gz);
                unlink($filePath);
                $finalFile = $gzPath;
                $finalName = $filename . '.gz';
            }
        }

        return [
            'backup_id'  => $backupId,
            'filename'   => $finalName,
            'path'       => $finalFile,
            'size'       => filesize($finalFile),
            'type'       => 'database',
            'created_at' => current_time('mysql'),
            'tables'     => count($tables),
        ];
    }
}
