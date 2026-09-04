<?php
namespace WPForge\Backup;

/**
 * Filesystem backup — archive specified directories.
 */
class FilesystemBackup
{
    private string $backupDir;

    public function __construct(string $backupDir)
    {
        $this->backupDir = $backupDir;
    }

    /**
     * Create a filesystem backup of specified paths.
     */
    public function create(array $options = []): array
    {
        $paths = $options['paths'] ?? [WP_CONTENT_DIR];
        $backupId = Manager::generateBackupId('files');
        $filename = $backupId . '.tar.gz';
        $filePath = $this->backupDir . $filename;

        // Use tar if available.
        if ($this->commandExists('tar')) {
            $pathArgs = implode(' ', array_map('escapeshellarg', $paths));
            $cmd = "tar -czf " . escapeshellarg($filePath) . " {$pathArgs} 2>/dev/null";
            exec($cmd, $output, $returnCode);

            if ($returnCode === 0 && file_exists($filePath)) {
                return [
                    'backup_id'  => $backupId,
                    'filename'   => $filename,
                    'path'       => $filePath,
                    'size'       => filesize($filePath),
                    'type'       => 'filesystem',
                    'created_at' => current_time('mysql'),
                    'paths'      => $paths,
                ];
            }
        }

        // Fallback: create a zip-like manifest JSON.
        $manifest = $this->createManifest($paths);
        $manifestPath = $this->backupDir . $backupId . '_manifest.json';
        file_put_contents($manifestPath, wp_json_encode($manifest, JSON_PRETTY_PRINT));

        return [
            'backup_id'  => $backupId,
            'filename'   => basename($manifestPath),
            'path'       => $manifestPath,
            'size'       => filesize($manifestPath),
            'type'       => 'filesystem',
            'created_at' => current_time('mysql'),
            'paths'      => $paths,
            'note'       => 'Manifest only — tar/gzip not available.',
        ];
    }

    /* ------------------------------------------------------------------ */

    private function createManifest(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            if (!file_exists($path)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                $relPath = str_replace(wp_normalize_path(ABSPATH), '', wp_normalize_path($file->getPathname()));
                $files[] = [
                    'path' => $relPath,
                    'size' => $file->getSize(),
                    'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                    'is_dir' => $file->isDir(),
                ];
            }
        }

        return [
            'created_at' => current_time('mysql'),
            'paths' => $paths,
            'files' => $files,
            'total' => count($files),
        ];
    }

    private function commandExists(string $cmd): bool
    {
        $check = function_exists('exec') ? @exec("which {$cmd} 2>/dev/null") : null;
        return !empty($check);
    }
}
