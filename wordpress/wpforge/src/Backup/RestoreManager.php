<?php

namespace WPForge\Backup;

/**
 * Restore manager — restore from backups.
 */
class RestoreManager
{
    private string $backupDir;

    public function __construct(string $backupDir = '')
    {
        $this->backupDir = rtrim($backupDir ?: WPFORGE_BACKUP_DIR, '/') . '/';
    }

    /**
     * Restore a database backup by restoring from a SQL file.
     */
    public function restoreDatabase(string $backupId): array
    {
        $manager = new Manager($this->backupDir);
        $info = $manager->getBackupInfo($backupId);

        if (!$info || $info['type'] !== 'database') {
            throw new \RuntimeException('Database backup not found: ' . $backupId);
        }

        $filePath = $info['path'];

        // Decompress if gzipped.
        if (str_ends_with($filePath, '.gz')) {
            $decompressed = $this->decompressGzip($filePath);
            if ($decompressed === false) {
                throw new \RuntimeException('Failed to decompress backup file');
            }
            $filePath = $decompressed;
        }

        $sql = file_get_contents($filePath);
        if ($sql === false) {
            throw new \RuntimeException('Failed to read backup file');
        }

        global $wpdb;
        $wpdb->query($sql);

        return [
            'success'   => true,
            'backup_id' => $backupId,
            'message'   => 'Database restored successfully',
        ];
    }

    private function decompressGzip(string $gzPath): string|false
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'wpforge_restore_');
        $gz = @gzopen($gzPath, 'rb');
        if (!$gz) {
            return false;
        }

        $fp = fopen($tempPath, 'wb');
        while (!gzeof($gz)) {
            fwrite($fp, gzread($gz, 8192));
        }
        fclose($fp);
        gzclose($gz);

        return $tempPath;
    }
}
