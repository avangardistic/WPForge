<?php

namespace WPForge\Filesystem;

/**
 * Read-only filesystem operations.
 */
class Reader
{
    private SecurityGuard $guard;

    public function __construct(string $root, array $allowedPaths = [])
    {
        $this->guard = new SecurityGuard($root, $allowedPaths);
    }

    /**
     * Read a file.
     */
    public function read(string $path): array
    {
        $fullPath = $this->guard->validatePath($path);

        if (!is_file($fullPath)) {
            throw new \RuntimeException('File not found: ' . $path);
        }

        $content = file_get_contents($fullPath);
        if ($content === false) {
            throw new \RuntimeException('Failed to read file: ' . $path);
        }

        return [
            'path'    => $path,
            'content' => $content,
            'size'    => filesize($fullPath),
            'hash'    => hash_file('sha256', $fullPath),
        ];
    }

    /**
     * Read a file and detect its type.
     */
    public function readWithType(string $path): array
    {
        $data = $this->read($path);
        $data['mime_type'] = mime_content_type($this->guard->validatePath($path)) ?: 'application/octet-stream';
        return $data;
    }
}
