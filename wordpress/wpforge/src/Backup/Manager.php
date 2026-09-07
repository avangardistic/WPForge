<?php

namespace WPForge\Backup;

/**
 * Backup manager — orchestrates database and filesystem backups.
 */
class Manager
{
    private string $backupDir;
    private DatabaseBackup $dbBackup;
    private FilesystemBackup $fsBackup;

    public function __construct(string $backupDir = '')
    {
        $this->backupDir = rtrim($backupDir ?: WPFORGE_BACKUP_DIR, '/') . '/';
        if (!is_dir($this->backupDir)) {
            wp_mkdir_p($this->backupDir);
        }
        $this->dbBackup = new DatabaseBackup($this->backupDir);
        $this->fsBackup = new FilesystemBackup($this->backupDir);
    }

    /**
     * Create a backup.
     */
    public function create(string $type = 'database', array $options = []): array
    {
        return match ($type) {
            'database' => $this->dbBackup->create($options),
            'filesystem' => $this->fsBackup->create($options),
            default => throw new \RuntimeException('Unsupported backup type: ' . $type),
        };
    }

    /**
     * List all backups.
     */
    public function listBackups(): array
    {
        $files = glob($this->backupDir . '*.*');
        $result = [];

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $filename = basename($file);
            $backupId = pathinfo($filename, PATHINFO_FILENAME);
            $ext = pathinfo($filename, PATHINFO_EXTENSION);

            $result[] = [
                'backup_id'  => $backupId,
                'filename'   => $filename,
                'path'       => $file,
                'size'       => filesize($file),
                'created_at' => date('Y-m-d H:i:s', filemtime($file)),
                'type'       => str_contains($filename, 'database') ? 'database' : 'filesystem',
            ];
        }

        usort($result, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        return $result;
    }

    /**
     * Get info about a specific backup.
     */
    public function getBackupInfo(string $backupId): ?array
    {
        $backups = $this->listBackups();
        foreach ($backups as $backup) {
            if ($backup['backup_id'] === $backupId) {
                return $backup;
            }
        }
        return null;
    }

    /**
     * Delete a backup.
     */
    public function deleteBackup(string $backupId): bool
    {
        $info = $this->getBackupInfo($backupId);
        if (!$info) {
            throw new \RuntimeException('Backup not found');
        }
        return unlink($info['path']);
    }

    /**
     * Clean up old backups beyond retention limit.
     */
    public function cleanup(int $maxBackups = 10): int
    {
        $backups = $this->listBackups();
        $deleted = 0;

        if (count($backups) > $maxBackups) {
            $toDelete = array_slice($backups, $maxBackups);
            foreach ($toDelete as $backup) {
                if (unlink($backup['path'])) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Generate a unique backup ID.
     */
    public static function generateBackupId(string $type = 'backup'): string
    {
        return 'bkp_' . $type . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    }
}
