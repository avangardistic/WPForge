<?php

namespace WPForge\Elementor;

/**
 * Elementor backup manager — backup and restore Elementor documents.
 */
class BackupManager
{
    private string $backupDir;

    public function __construct(string $backupDir = '')
    {
        $this->backupDir = $backupDir ?: WPFORGE_BACKUP_DIR . '/elementor';
        if (!is_dir($this->backupDir)) {
            wp_mkdir_p($this->backupDir);
        }
    }

    /**
     * Create a backup of an Elementor document.
     */
    public function createBackup(int $documentId): array
    {
        $adapter = new Adapter();
        $document = $adapter->getDocument($documentId);

        if (!$document) {
            throw new \RuntimeException('Document not found');
        }

        $backupId = 'el_' . $documentId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $filename = $backupId . '.json';
        $filePath = $this->backupDir . '/' . $filename;

        $backupData = [
            'backup_id'  => $backupId,
            'document_id' => $documentId,
            'created_at' => current_time('mysql'),
            'document'   => $document,
        ];

        $result = file_put_contents($filePath, wp_json_encode($backupData, JSON_PRETTY_PRINT));
        if ($result === false) {
            throw new \RuntimeException('Failed to write backup file');
        }

        return [
            'backup_id'  => $backupId,
            'document_id' => $documentId,
            'filename'   => $filename,
            'size'       => filesize($filePath),
            'created_at' => current_time('mysql'),
        ];
    }

    /**
     * List backups for a specific document.
     */
    public function listBackups(int $documentId): array
    {
        $pattern = $this->backupDir . '/el_' . $documentId . '_*.json';
        $files = glob($pattern);
        $backups = [];

        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $backups[] = [
                    'backup_id'  => $data['backup_id'],
                    'document_id' => $data['document_id'],
                    'filename'   => basename($file),
                    'size'       => filesize($file),
                    'created_at' => $data['created_at'],
                ];
            }
        }

        // Sort by date descending.
        usort($backups, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        return $backups;
    }

    /**
     * Restore a document from a backup.
     */
    public function restoreBackup(string $backupId): array
    {
        $files = glob($this->backupDir . '/' . $backupId . '.json');
        if (empty($files)) {
            throw new \RuntimeException('Backup not found: ' . $backupId);
        }

        $data = json_decode(file_get_contents($files[0]), true);
        if (!$data || !isset($data['document'])) {
            throw new \RuntimeException('Invalid backup file');
        }

        $adapter = new Adapter();
        $documentId = (int) $data['document_id'];

        return $adapter->updateDocument($documentId, [
            'title'         => $data['document']['title'] ?? '',
            'status'        => $data['document']['status'] ?? 'publish',
            'elementor_data' => $data['document']['elementor_data'] ?? [],
        ]);
    }

    /**
     * Delete a backup.
     */
    public function deleteBackup(string $backupId): bool
    {
        $files = glob($this->backupDir . '/' . $backupId . '.json');
        if (empty($files)) {
            throw new \RuntimeException('Backup not found');
        }
        return unlink($files[0]);
    }
}
