<?php
namespace WPForge\Logging;

/**
 * Logging manager — orchestrates log writing, rotation, and filtering.
 */
class Manager
{
    private string $logDir;
    private array $config;

    public function __construct(string $logDir = '', array $config = [])
    {
        $this->logDir = $logDir ?: WPFORGE_LOG_DIR;
        $this->config = array_merge([
            'enabled'     => true,
            'level'       => 'info',
            'max_files'   => 10,
            'max_size'    => 10 * 1024 * 1024, // 10MB
            'log_headers' => false,
            'log_payload' => false,
        ], $config);

        if (!is_dir($this->logDir)) {
            wp_mkdir_p($this->logDir);
        }
    }

    /**
     * Write a log entry.
     */
    public function write(string $level, string $message, array $context = []): void
    {
        if (!$this->config['enabled']) {
            return;
        }

        if (!$this->shouldLog($level)) {
            return;
        }

        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level'     => strtoupper($level),
            'message'   => $message,
            'context'   => $this->filterContext($context),
        ];

        $logFile = $this->getLogFileName();
        $line = wp_json_encode($entry) . "\n";

        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

        $this->rotateIfNeeded($logFile);
    }

    /**
     * Log levels.
     */
    public function debug(string $msg, array $ctx = []): void { $this->write('debug', $msg, $ctx); }
    public function info(string $msg, array $ctx = []): void { $this->write('info', $msg, $ctx); }
    public function warning(string $msg, array $ctx = []): void { $this->write('warning', $msg, $ctx); }
    public function error(string $msg, array $ctx = []): void { $this->write('error', $msg, $ctx); }

    /**
     * Read recent log entries.
     */
    public function read(int $limit = 100, string $level = ''): array
    {
        $logFile = $this->getLogFileName();
        if (!file_exists($logFile)) {
            return [];
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $entries = [];

        foreach (array_reverse($lines) as $line) {
            if (count($entries) >= $limit) {
                break;
            }

            $entry = json_decode($line, true);
            if (!$entry) {
                continue;
            }

            if ($level && strtoupper($entry['level'] ?? '') !== strtoupper($level)) {
                continue;
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * Clear current log file.
     */
    public function clear(): void
    {
        $logFile = $this->getLogFileName();
        if (file_exists($logFile)) {
            unlink($logFile);
        }
    }

    /* ------------------------------------------------------------------ */

    private function getLogFileName(): string
    {
        return $this->logDir . '/wpforge-' . date('Y-m-d') . '.log';
    }

    private function shouldLog(string $level): bool
    {
        $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
        $minLevel = $levels[$this->config['level']] ?? 1;
        $currentLevel = $levels[strtolower($level)] ?? 1;
        return $currentLevel >= $minLevel;
    }

    private function filterContext(array $context): array
    {
        if (!$this->config['log_headers']) {
            unset($context['headers']);
        }
        if (!$this->config['log_payload']) {
            unset($context['payload']);
        }
        return $context;
    }

    private function rotateIfNeeded(string $logFile): void
    {
        if (!file_exists($logFile)) {
            return;
        }

        if (filesize($logFile) > $this->config['max_size']) {
            $rotated = $logFile . '.' . date('His');
            rename($logFile, $rotated);
        }

        // Cleanup old files.
        $files = glob($this->logDir . '/wpforge-*.log*');
        if (count($files) > $this->config['max_files']) {
            usort($files, fn($a, $b) => filemtime($a) - filemtime($b));
            $toDelete = array_slice($files, 0, count($files) - $this->config['max_files']);
            foreach ($toDelete as $file) {
                unlink($file);
            }
        }
    }
}
