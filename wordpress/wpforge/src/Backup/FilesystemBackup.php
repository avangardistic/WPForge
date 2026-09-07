<?php

namespace WPForge\Backup;

/**
 * Filesystem backup — archive specified directories.
 *
 * Uses PHP's bundled ZipArchive rather than shelling out to `tar`, so it works
 * on hosts that disable exec()/shell_exec() (common) and on Windows, and passes
 * WordPress.org's plugin review, which flags process-execution functions. When
 * the zip extension is unavailable it falls back to a JSON manifest.
 */
class FilesystemBackup
{
    private string $backupDir;

    public function __construct(string $backupDir)
    {
        $this->backupDir = $backupDir;
    }

    /**
     * Create a filesystem backup of the specified paths.
     *
     * @param array{paths?: string[]} $options
     */
    public function create(array $options = []): array
    {
        $paths = $options['paths'] ?? [WP_CONTENT_DIR];
        $backupId = Manager::generateBackupId('files');

        if (class_exists('\ZipArchive')) {
            $filename = $backupId . '.zip';
            $filePath = $this->backupDir . $filename;

            if ($this->createZipArchive($paths, $filePath) && file_exists($filePath)) {
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

        // Fallback: a JSON manifest describing the tree, when zip is unavailable.
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
            'note'       => 'Manifest only — the PHP zip extension is not available.',
        ];
    }

    /* ------------------------------------------------------------------ */

    /**
     * Archive the given paths into a zip using ZipArchive (no shell).
     *
     * @param string[] $paths
     */
    private function createZipArchive(array $paths, string $filePath): bool
    {
        $zip = new \ZipArchive();
        if ($zip->open($filePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $root = rtrim(wp_normalize_path(ABSPATH), '/') . '/';

        foreach ($paths as $path) {
            if (!file_exists($path)) {
                continue;
            }

            if (is_file($path)) {
                $zip->addFile($path, basename($path));
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                $absolute = wp_normalize_path($file->getPathname());
                $local = ltrim(str_replace($root, '', $absolute), '/');

                if ($file->isDir()) {
                    $zip->addEmptyDir($local);
                } else {
                    $zip->addFile($file->getPathname(), $local);
                }
            }
        }

        return $zip->close();
    }

    /**
     * @param string[] $paths
     */
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
                    'path'     => $relPath,
                    'size'     => $file->getSize(),
                    'modified' => gmdate('Y-m-d H:i:s', $file->getMTime()),
                    'is_dir'   => $file->isDir(),
                ];
            }
        }

        return [
            'created_at' => current_time('mysql'),
            'paths'      => $paths,
            'files'      => $files,
            'total'      => count($files),
        ];
    }
}
