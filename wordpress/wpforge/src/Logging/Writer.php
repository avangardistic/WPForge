<?php

namespace WPForge\Logging;

/**
 * Log writer — handles low-level file writing.
 */
class Writer
{
    private string $directory;

    public function __construct(string $directory = '')
    {
        $this->directory = $directory ?: WPFORGE_LOG_DIR;
    }

    /**
     * Append a line to a log file.
     */
    public function append(string $filename, string $line): bool
    {
        $path = $this->directory . '/' . $filename;
        return file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX) !== false;
    }

    /**
     * Write to a file (overwrite).
     */
    public function write(string $filename, string $content): bool
    {
        $path = $this->directory . '/' . $filename;
        return file_put_contents($path, $content, LOCK_EX) !== false;
    }

    /**
     * Read the contents of a log file.
     */
    public function read(string $filename): ?string
    {
        $path = $this->directory . '/' . $filename;
        if (!file_exists($path)) {
            return null;
        }
        return file_get_contents($path);
    }

    /**
     * List log files in the directory.
     */
    public function listFiles(string $pattern = '*'): array
    {
        $files = glob($this->directory . '/' . $pattern);
        $result = [];

        foreach ($files as $file) {
            if (is_file($file)) {
                $result[] = [
                    'name'     => basename($file),
                    'size'     => filesize($file),
                    'modified' => date('Y-m-d H:i:s', filemtime($file)),
                ];
            }
        }

        usort($result, fn($a, $b) => strcmp($b['modified'], $a['modified']));
        return $result;
    }

    /**
     * Delete a log file.
     */
    public function delete(string $filename): bool
    {
        $path = $this->directory . '/' . $filename;
        if (file_exists($path)) {
            return unlink($path);
        }
        return false;
    }
}
