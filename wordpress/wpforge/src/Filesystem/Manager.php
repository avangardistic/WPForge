<?php

namespace WPForge\Filesystem;

/**
 * Filesystem manager — list, read, write, delete files within a secure root.
 */
class Manager
{
    private string $root;
    private SecurityGuard $guard;

    public function __construct(string $root, array $allowedPaths = [])
    {
        $this->root = rtrim(wp_normalize_path($root), '/') . '/';
        $this->guard = new SecurityGuard($this->root, $allowedPaths);
    }

    /**
     * List a directory's contents.
     */
    public function listDirectory(string $path): array
    {
        $fullPath = $this->guard->validatePath($path);

        if (!is_dir($fullPath)) {
            throw new \RuntimeException('Directory not found: ' . $path);
        }

        $files = scandir($fullPath);
        $result = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $fullFile = $fullPath . '/' . $file;
            $result[] = [
                'name'       => $file,
                'path'       => ltrim(str_replace($this->root, '', wp_normalize_path($fullFile)), '/'),
                'type'       => is_dir($fullFile) ? 'directory' : 'file',
                'size'       => is_file($fullFile) ? filesize($fullFile) : 0,
                'modified'   => date('Y-m-d H:i:s', filemtime($fullFile)),
                'permissions' => substr(sprintf('%o', fileperms($fullFile)), -4),
            ];
        }

        // Sort: directories first, then by name.
        usort($result, function ($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return $result;
    }

    /**
     * Read a file's contents.
     */
    public function readFile(string $path): array
    {
        $fullPath = $this->guard->validatePath($path);

        if (!is_file($fullPath)) {
            throw new \RuntimeException('File not found: ' . $path);
        }
        if (!is_readable($fullPath)) {
            throw new \RuntimeException('File is not readable: ' . $path);
        }

        $content = file_get_contents($fullPath);
        if ($content === false) {
            throw new \RuntimeException('Failed to read file: ' . $path);
        }

        return [
            'path'     => $path,
            'content'  => $content,
            'size'     => filesize($fullPath),
            'hash'     => hash_file('sha256', $fullPath),
            'modified' => date('Y-m-d H:i:s', filemtime($fullPath)),
        ];
    }

    /**
     * Write content to a file.
     */
    public function writeFile(string $path, string $content, bool $createBackup = true): array
    {
        $fullPath = $this->guard->validatePath($path);

        $backup = null;
        if (file_exists($fullPath) && $createBackup) {
            $backup = $this->readFile($path);
        }

        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $bytes = file_put_contents($fullPath, $content);
        if ($bytes === false) {
            throw new \RuntimeException('Failed to write file: ' . $path);
        }

        return [
            'success'       => true,
            'path'          => $path,
            'bytes_written' => $bytes,
            'hash'          => hash('sha256', $content),
            'backup'        => $backup,
            'timestamp'     => current_time('mysql'),
        ];
    }

    /**
     * Delete a file.
     */
    public function deleteFile(string $path): array
    {
        $fullPath = $this->guard->validatePath($path);

        if (!file_exists($fullPath)) {
            throw new \RuntimeException('File not found: ' . $path);
        }
        if (!is_file($fullPath)) {
            throw new \RuntimeException('Cannot delete directory with deleteFile: ' . $path);
        }

        $deleted = unlink($fullPath);
        if (!$deleted) {
            throw new \RuntimeException('Failed to delete file: ' . $path);
        }

        return ['success' => true, 'path' => $path, 'deleted' => true];
    }

    /**
     * Create a directory.
     */
    public function createDirectory(string $path): array
    {
        $fullPath = $this->guard->validatePath($path);

        if (is_dir($fullPath)) {
            return ['success' => true, 'path' => $path, 'already_exists' => true];
        }

        $created = mkdir($fullPath, 0755, true);
        if (!$created) {
            throw new \RuntimeException('Failed to create directory: ' . $path);
        }

        return ['success' => true, 'path' => $path, 'created' => true];
    }

    /**
     * Get info about a file or directory.
     */
    public function getFileInfo(string $path): array
    {
        $fullPath = $this->guard->validatePath($path);

        if (!file_exists($fullPath)) {
            throw new \RuntimeException('Path not found: ' . $path);
        }

        return [
            'path'        => $path,
            'type'        => is_dir($fullPath) ? 'directory' : 'file',
            'size'        => is_file($fullPath) ? filesize($fullPath) : 0,
            'modified'    => date('Y-m-d H:i:s', filemtime($fullPath)),
            'permissions' => substr(sprintf('%o', fileperms($fullPath)), -4),
            'readable'    => is_readable($fullPath),
            'writable'    => is_writable($fullPath),
        ];
    }
}
