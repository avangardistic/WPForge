<?php

namespace WPForge\Security;

/**
 * Filesystem path validator — prevents path traversal attacks.
 */
class PathValidator
{
    private string $root;
    private array $allowedPaths;
    private array $forbiddenPatterns;

    public function __construct(string $root, array $allowedPaths = [], array $forbiddenPatterns = [])
    {
        $this->root = rtrim(wp_normalize_path($root), '/') . '/';
        $this->allowedPaths = array_map(fn($p) => rtrim(wp_normalize_path($p), '/'), $allowedPaths);

        $this->forbiddenPatterns = !empty($forbiddenPatterns)
            ? $forbiddenPatterns
            : [
                '/\.\.\//',
                '/\.\.\\\\/',
                '/^\/etc\//',
                '/^\/proc\//',
                '/^\/sys\//',
                '/^\/dev\//',
                '/\/wp-config\.php$/',
                '/\/\.htaccess$/',
            ];
    }

    /**
     * Validate and resolve a relative path to an absolute path within the root.
     *
     * @throws \RuntimeException if the path is invalid or outside the root.
     */
    public function validatePath(string $path): string
    {
        $normalized = $this->normalizePath($path);

        // Check forbidden patterns
        foreach ($this->forbiddenPatterns as $pattern) {
            if (preg_match($pattern, $normalized)) {
                throw new \RuntimeException('Path contains forbidden pattern: ' . $path);
            }
        }

        $fullPath = $this->root . ltrim($normalized, '/');
        $realPath = realpath($fullPath) ?: $fullPath;

        // Ensure the resolved path stays within root
        if (strpos($realPath, $this->root) !== 0) {
            throw new \RuntimeException('Path is outside allowed root: ' . $path);
        }

        // Check allowed paths whitelist if configured
        if (!empty($this->allowedPaths)) {
            $allowed = false;
            foreach ($this->allowedPaths as $allowedPath) {
                if (strpos($realPath, $allowedPath) === 0) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                throw new \RuntimeException('Path is not in allowed directories: ' . $path);
            }
        }

        return $realPath;
    }

    /**
     * Check if a path is within the root without throwing.
     */
    public function isPathValid(string $path): bool
    {
        try {
            $this->validatePath($path);
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * Normalize a path: remove leading slashes, collapse separators, resolve . and ..
     */
    public function normalizePath(string $path): string
    {
        $path = ltrim($path, '/');
        $path = preg_replace('#/+#', '/', $path);

        $parts = explode('/', $path);
        $resolved = [];

        foreach ($parts as $part) {
            if ($part === '.' || $part === '') {
                continue;
            }
            if ($part === '..') {
                array_pop($resolved);
                continue;
            }
            $resolved[] = $part;
        }

        return implode('/', $resolved);
    }
}
