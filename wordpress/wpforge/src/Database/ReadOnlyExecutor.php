<?php

namespace WPForge\Database;

/**
 * Read-only SQL executor — only allows SELECT queries.
 */
class ReadOnlyExecutor
{
    /** @var \wpdb */
    private $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Execute a read-only query.
     */
    public function execute(string $sql, array $params = [], int $maxRows = 1000): array
    {
        $trimmed = ltrim($sql);
        if (stripos($trimmed, 'SELECT') !== 0) {
            throw new \RuntimeException('Only SELECT queries are allowed in read-only mode.');
        }

        // Enforce LIMIT if not already present
        if (stripos($sql, 'LIMIT') === false) {
            $sql = rtrim($sql, ';') . " LIMIT {$maxRows}";
        }

        $result = $this->wpdb->get_results($sql, ARRAY_A);

        if ($result === false) {
            throw new \RuntimeException('Query error: ' . $this->wpdb->last_error);
        }

        return [
            'success' => true,
            'rows'    => count($result),
            'data'    => $result,
            'query'   => $sql,
        ];
    }
}
