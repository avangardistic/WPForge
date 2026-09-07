<?php

namespace WPForge\Filesystem;

/**
 * Filesystem security guard — prevents path traversal and enforces root boundary.
 */
class SecurityGuard
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
            ];
    }

    /**
     * Validate a path and return the resolved absolute path.
     *
     * @throws \RuntimeException if the path is invalid.
     */
    public function validatePath(string $path): string
    {
        $normalized = $this->normalizePath($path);

        foreach ($this->forbiddenPatterns as $pattern) {
            if (preg_match($pattern, $normalized)) {
                throw new \RuntimeException('Path contains forbidden pattern: ' . $path);
            }
        }

        $fullPath = $this->root . ltrim($normalized, '/');
        $realPath = realpath($fullPath) ?: $fullPath;

        if (strpos($realPath, $this->root) !== 0) {
            throw new \RuntimeException('Path is outside allowed root: ' . $path);
        }

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
     * Normalize a path: strip leading slashes, collapse separators, resolve . and ..
     */
    private function normalizePath(string $path): string
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
