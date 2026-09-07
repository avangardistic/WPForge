<?php

namespace WPForge\Logging;

/**
 * Log rotator — manages log file rotation and cleanup.
 */
class Rotator
{
    private string $directory;
    private int $maxSize;
    private int $maxFiles;

    public function __construct(string $directory = '', int $maxSize = 10485760, int $maxFiles = 10)
    {
        $this->directory = $directory ?: WPFORGE_LOG_DIR;
        $this->maxSize = $maxSize;
        $this->maxFiles = $maxFiles;
    }

    /**
     * Rotate a log file if it exceeds max size.
     */
    public function rotate(string $filename): void
    {
        $path = $this->directory . '/' . $filename;
        if (!file_exists($path) || filesize($path) <= $this->maxSize) {
            return;
        }

        $rotated = $path . '.' . date('Ymd_His');
        rename($path, $rotated);

        $this->cleanup();
    }

    /**
     * Remove old log files beyond the max count.
     */
    public function cleanup(): void
    {
        $files = glob($this->directory . '/wpforge*.log*');
        if (!$files || count($files) <= $this->maxFiles) {
            return;
        }

        usort($files, fn($a, $b) => filemtime($a) - filemtime($b));

        $toDelete = array_slice($files, 0, count($files) - $this->maxFiles);
        foreach ($toDelete as $file) {
            unlink($file);
        }
    }

    /**
     * Get total size of all log files.
     */
    public function getTotalSize(): int
    {
        $total = 0;
        $files = glob($this->directory . '/wpforge*');
        foreach ($files as $file) {
            if (is_file($file)) {
                $total += filesize($file);
            }
        }
        return $total;
    }
}
