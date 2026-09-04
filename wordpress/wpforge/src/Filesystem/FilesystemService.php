<?php

namespace WPForge\Filesystem;

use WP_Error;
use WPForge\Core\Config;
use WPForge\Security\Validator;

/**
 * Filesystem service for controlled file operations
 */
class FilesystemService
{
    private Config $config;
    private Validator $validator;

    public function __construct()
    {
        $this->config = new Config();
        $this->validator = new Validator();
    }

    /**
     * Read a file
     */
    public function readFile(string $path): array|WP_Error
    {
        $validated_path = $this->validatePath($path);
        
        if (false === $validated_path) {
            return new WP_Error('invalid_path', 'Invalid or unauthorized path.', ['status' => 400]);
        }

        if (!file_exists($validated_path)) {
            return new WP_Error('file_not_found', 'File not found.', ['status' => 404]);
        }

        if (!is_readable($validated_path)) {
            return new WP_Error('file_not_readable', 'File is not readable.', ['status' => 403]);
        }

        // Check file size
        $size = filesize($validated_path);
        $max_size = 10 * 1024 * 1024; // 10MB default max
        
        if ($size > $max_size) {
            return new WP_Error('file_too_large', 'File exceeds maximum size limit.', ['status' => 400]);
        }

        $content = file_get_contents($validated_path);
        
        if ($content === false) {
            return new WP_Error('read_failed', 'Failed to read file.', ['status' => 500]);
        }

        return [
            'path' => $path,
            'absolute_path' => $validated_path,
            'size' => $size,
            'modified' => date('Y-m-d H:i:s', filemtime($validated_path)),
            'content' => $content,
            'mime_type' => $this->getMimeType($validated_path),
        ];
    }

    /**
     * Write to a file
     */
    public function writeFile(string $path, string $content): array|WP_Error
    {
        $validated_path = $this->validatePath($path);
        
        if (false === $validated_path) {
            return new WP_Error('invalid_path', 'Invalid or unauthorized path.', ['status' => 400]);
        }

        // Check if file exists for hash comparison
        $old_hash = null;
        if (file_exists($validated_path)) {
            $old_hash = md5_file($validated_path);
        }

        // Ensure parent directory exists
        $parent_dir = dirname($validated_path);
        if (!is_dir($parent_dir)) {
            if (!wp_mkdir_p($parent_dir)) {
                return new WP_Error('mkdir_failed', 'Failed to create parent directory.', ['status' => 500]);
            }
        }

        $result = file_put_contents($validated_path, $content);
        
        if ($result === false) {
            return new WP_Error('write_failed', 'Failed to write file.', ['status' => 500]);
        }

        $new_hash = md5($content);

        return [
            'path' => $path,
            'absolute_path' => $validated_path,
            'bytes_written' => $result,
            'old_hash' => $old_hash,
            'new_hash' => $new_hash,
            'modified' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Delete a file
     */
    public function deleteFile(string $path): array|WP_Error
    {
        $validated_path = $this->validatePath($path);
        
        if (false === $validated_path) {
            return new WP_Error('invalid_path', 'Invalid or unauthorized path.', ['status' => 400]);
        }

        if (!file_exists($validated_path)) {
            return new WP_Error('file_not_found', 'File not found.', ['status' => 404]);
        }

        if (!is_writable($validated_path)) {
            return new WP_Error('file_not_writable', 'File is not writable.', ['status' => 403]);
        }

        // Don't allow deleting critical WordPress files
        if ($this->isCriticalFile($validated_path)) {
            return new WP_Error('critical_file', 'Cannot delete critical WordPress file.', ['status' => 403]);
        }

        $result = unlink($validated_path);
        
        if (!$result) {
            return new WP_Error('delete_failed', 'Failed to delete file.', ['status' => 500]);
        }

        return [
            'path' => $path,
            'absolute_path' => $validated_path,
            'deleted' => true,
        ];
    }

    /**
     * List directory contents
     */
    public function listDirectory(string $path = '', array $options = []): array|WP_Error
    {
        $defaults = [
            'recursive' => false,
            'include_files' => true,
            'include_dirs' => true,
            'exclude_patterns' => ['.*', '*.log', 'node_modules', 'vendor'],
        ];
        
        $options = wp_parse_args($options, $defaults);

        $validated_path = $this->validatePath($path);
        
        if (false === $validated_path) {
            return new WP_Error('invalid_path', 'Invalid or unauthorized path.', ['status' => 400]);
        }

        if (!is_dir($validated_path)) {
            return new WP_Error('not_a_directory', 'Path is not a directory.', ['status' => 400]);
        }

        if (!is_readable($validated_path)) {
            return new WP_Error('directory_not_readable', 'Directory is not readable.', ['status' => 403]);
        }

        $items = $this->scanDirectory($validated_path, $options);

        return [
            'path' => $path,
            'absolute_path' => $validated_path,
            'items' => $items,
            'total_count' => count($items),
        ];
    }

    /**
     * Create a directory
     */
    public function createDirectory(string $path): array|WP_Error
    {
        $validated_path = $this->validatePath($path);
        
        if (false === $validated_path) {
            return new WP_Error('invalid_path', 'Invalid or unauthorized path.', ['status' => 400]);
        }

        if (file_exists($validated_path)) {
            return new WP_Error('already_exists', 'Path already exists.', ['status' => 400]);
        }

        $result = wp_mkdir_p($validated_path);
        
        if (!$result) {
            return new WP_Error('mkdir_failed', 'Failed to create directory.', ['status' => 500]);
        }

        return [
            'path' => $path,
            'absolute_path' => $validated_path,
            'created' => true,
        ];
    }

    /**
     * Normalize and validate a path
     */
    public function normalizePath(string $path): string
    {
        $root = $this->config->getFilesystemRoot();
        $path = ltrim($path, '/');
        return rtrim($root, '/') . '/' . $path;
    }

    /**
     * Validate path security
     */
    private function validatePath(string $path): string|false
    {
        if (empty($path)) {
            return false;
        }

        // Check for traversal attempts
        if ($this->validator->containsTraversal($path)) {
            return false;
        }

        $root = $this->config->getFilesystemRoot();
        $full_path = $this->normalizePath($path);

        // Resolve real path if exists
        if (file_exists($full_path)) {
            $real_path = realpath($full_path);
            $real_root = realpath($root);
            
            if ($real_path && $real_root) {
                if (strpos($real_path, $real_root) !== 0) {
                    return false;
                }
                return $real_path;
            }
        }

        // For non-existent paths, check parent directory
        $parent = dirname($full_path);
        if (file_exists($parent)) {
            $real_parent = realpath($parent);
            $real_root = realpath($root);
            
            if ($real_parent && $real_root) {
                if (strpos($real_parent, $real_root) !== 0) {
                    return false;
                }
            }
        }

        return $full_path;
    }

    /**
     * Scan directory recursively
     */
    private function scanDirectory(string $path, array $options, int $depth = 0): array
    {
        $items = [];
        $entries = scandir($path);
        
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            // Check exclude patterns
            $skip = false;
            foreach ($options['exclude_patterns'] as $pattern) {
                if (fnmatch($pattern, $entry)) {
                    $skip = true;
                    break;
                }
            }
            
            if ($skip) {
                continue;
            }

            $full_path = trailingslashit($path) . $entry;
            $is_dir = is_dir($full_path);
            
            if ($is_dir && !$options['include_dirs']) {
                continue;
            }
            
            if (!$is_dir && !$options['include_files']) {
                continue;
            }

            $item = [
                'name' => $entry,
                'type' => $is_dir ? 'directory' : 'file',
                'size' => $is_dir ? null : filesize($full_path),
                'modified' => date('Y-m-d H:i:s', filemtime($full_path)),
            ];

            if ($is_dir && $options['recursive'] && $depth < 10) {
                $item['children'] = $this->scanDirectory($full_path, $options, $depth + 1);
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Check if a file is critical to WordPress operation
     */
    private function isCriticalFile(string $path): bool
    {
        $critical_files = [
            'wp-config.php',
            '.htaccess',
            'web.config',
        ];

        $basename = basename($path);
        
        foreach ($critical_files as $critical) {
            if ($basename === $critical) {
                return true;
            }
        }

        // Check if in wp-admin or wp-includes root
        if (strpos($path, '/wp-admin/') !== false || strpos($path, '/wp-includes/') !== false) {
            // Allow modifications in subdirectories but not core files
            return false;
        }

        return false;
    }

    /**
     * Get MIME type of a file
     */
    private function getMimeType(string $path): string
    {
        if (function_exists('mime_content_type')) {
            return mime_content_type($path);
        }
        
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime_types = [
            'php' => 'application/x-php',
            'js' => 'application/javascript',
            'css' => 'text/css',
            'html' => 'text/html',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'txt' => 'text/plain',
            'md' => 'text/markdown',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
        ];
        
        return $mime_types[$ext] ?? 'application/octet-stream';
    }
}
