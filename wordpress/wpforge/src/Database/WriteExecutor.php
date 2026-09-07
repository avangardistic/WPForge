<?php

namespace WPForge\Database;

/**
 * Write SQL executor — allows INSERT, UPDATE, DELETE when explicitly enabled.
 *
 * This is DISABLED by default and requires configuration to enable.
 */
class WriteExecutor
{
    /** @var \wpdb */
    private $wpdb;
    private bool $enabled;

    public function __construct(bool $enabled = false)
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->enabled = $enabled;
    }

    /**
     * Execute a write query (INSERT, UPDATE, DELETE, etc.).
     */
    public function execute(string $sql, array $params = []): array
    {
        if (!$this->enabled) {
            throw new \RuntimeException('Write operations are disabled. Enable in configuration.');
        }

        $trimmed = ltrim($sql);
        $firstWord = strtoupper(substr($trimmed, 0, strpos($trimmed, ' ') ?: strlen($trimmed)));
        $allowed = ['INSERT', 'UPDATE', 'DELETE', 'REPLACE'];

        if (!in_array($firstWord, $allowed, true)) {
            throw new \RuntimeException('Only INSERT, UPDATE, DELETE, and REPLACE are allowed.');
        }

        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $placeholder = ':' . $key;
                $safeValue = is_string($value) ? $this->wpdb->prepare('%s', $value) : (string) $value;
                $sql = str_replace($placeholder, $safeValue, $sql);
            }
        }

        $result = $this->wpdb->query($sql);

        if ($result === false) {
            throw new \RuntimeException('Query error: ' . $this->wpdb->last_error);
        }

        return [
            'success' => true,
            'affected_rows' => $this->wpdb->rows_affected,
            'last_insert_id' => $this->wpdb->insert_id,
            'query' => $sql,
        ];
    }
}
