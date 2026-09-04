<?php
namespace WPForge\Backup;

/**
 * Cleanup manager — remove old backups based on retention policies.
 */
class CleanupManager
{
    private string $backupDir;

    public function __construct(string $backupDir = '')
    {
        $this->backupDir = rtrim($backupDir ?: WPFORGE_BACKUP_DIR, '/') . '/';
    }

    /**
     * Remove backups older than a given number of days.
     */
    public function cleanupByAge(int $retentionDays = 30): int
    {
        $deleted = 0;
        $cutoff = time() - ($retentionDays * 86400);

        $files = glob($this->backupDir . '*.*');
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            if (filemtime($file) < $cutoff) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Remove backups beyond a maximum count (keep newest).
     */
    public function cleanupByCount(int $maxBackups = 10): int
    {
        $manager = new Manager($this->backupDir);
        return $manager->cleanup($maxBackups);
    }

    /**
     * Get disk usage of the backup directory.
     */
    public function getDiskUsage(): array
    {
        $totalSize = 0;
        $fileCount = 0;

        $files = glob($this->backupDir . '*.*');
        foreach ($files as $file) {
            if (is_file($file)) {
                $totalSize += filesize($file);
                $fileCount++;
            }
        }

        return [
            'total_size'  => $totalSize,
            'total_human' => size_format($totalSize),
            'file_count'  => $fileCount,
        ];
    }
}
