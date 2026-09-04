<?php
namespace WPForge\Filesystem;

/**
 * Write filesystem operations with backup support.
 */
class Writer
{
    private SecurityGuard $guard;

    public function __construct(string $root, array $allowedPaths = [])
    {
        $this->guard = new SecurityGuard($root, $allowedPaths);
    }

    /**
     * Write content to a file, optionally creating a backup first.
     */
    public function write(string $path, string $content, bool $backup = true): array
    {
        $fullPath = $this->guard->validatePath($path);

        $backupData = null;
        if (file_exists($fullPath) && $backup) {
            $raw = file_get_contents($fullPath);
            $backupData = [
                'content' => $raw,
                'hash'    => hash('sha256', $raw),
                'size'    => filesize($fullPath),
            ];
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
            'backup'        => $backupData,
        ];
    }

    /**
     * Append content to a file.
     */
    public function append(string $path, string $content): array
    {
        $fullPath = $this->guard->validatePath($path);

        $fp = fopen($fullPath, 'a');
        if (!$fp) {
            throw new \RuntimeException('Failed to open file for append: ' . $path);
        }

        $bytes = fwrite($fp, $content);
        fclose($fp);

        return [
            'success' => true,
            'path'    => $path,
            'bytes_written' => $bytes,
        ];
    }
}
