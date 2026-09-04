<?php
namespace WPForge\Database;

/**
 * Database inspector — status, table listing, schema description.
 */
class Inspector
{
    /** @var \wpdb */
    private $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Get database connection status.
     */
    public function getStatus(): array
    {
        return [
            'connected'  => !empty($this->wpdb),
            'database'    => defined('DB_NAME') ? DB_NAME : '',
            'prefix'       => $this->wpdb->prefix,
            'version'      => $this->wpdb->db_version(),
            'tables'       => $this->getTableCount(),
        ];
    }

    /**
     * List all tables with row counts and sizes.
     */
    public function listTables(): array
    {
        $tables = $this->wpdb->get_results("SHOW TABLES", ARRAY_N);
        $result = [];

        foreach ($tables as $row) {
            $name = $row[0];
            $rowCount = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM `{$name}`");
            $result[] = [
                'name' => $name,
                'rows' => $rowCount,
                'size' => $this->getTableSize($name),
            ];
        }

        return $result;
    }

    /**
     * Describe a table's columns.
     */
    public function describeTable(string $table): array
    {
        $safe = $this->wpdb->escape($table);
        return $this->wpdb->get_results("DESCRIBE `{$safe}`", ARRAY_A) ?: [];
    }

    /**
     * Execute a read-only SELECT query.
     */
    public function query(string $sql, array $params = []): array
    {
        $trimmed = ltrim($sql);
        if (stripos($trimmed, 'SELECT') !== 0) {
            throw new \RuntimeException('Only SELECT queries are allowed in read-only mode.');
        }

        $preparedSql = $this->prepareQuery($sql, $params);

        $result = $this->wpdb->get_results($preparedSql, ARRAY_A);

        if ($result === false) {
            throw new \RuntimeException('Query failed: ' . $this->wpdb->last_error);
        }

        return [
            'success' => true,
            'rows'    => count($result),
            'data'    => $result,
            'query'   => $preparedSql,
        ];
    }

    /**
     * Get table information (CREATE TABLE statement).
     */
    public function getTableInfo(string $table): ?string
    {
        $safe = $this->wpdb->escape($table);
        $row = $this->wpdb->get_row("SHOW CREATE TABLE `{$safe}`", ARRAY_N);
        return $row ? $row[1] : null;
    }

    /* ------------------------------------------------------------------ */

    private function getTableCount(): int
    {
        $tables = $this->wpdb->get_results("SHOW TABLES", ARRAY_N);
        return count($tables);
    }

    private function getTableSize(string $table): string
    {
        $size = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT ROUND(SUM(data_length + index_length) / 1024) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                $this->wpdb->dbname,
                $table
            )
        );

        return $size ? size_format((int) $size * 1024, 2) : '0 B';
    }

    /**
     * Prepare SQL query by converting named parameters to positional placeholders.
     * 
     * This method converts :param placeholders to ? and uses $wpdb->prepare()
     * to safely escape all parameter values, preventing SQL injection.
     * 
     * @param string $sql SQL query with :named placeholders
     * @param array $params Associative array of parameters
     * @return string Safely prepared SQL query
     */
    private function prepareQuery(string $sql, array $params): string
    {
        if (empty($params)) {
            return $sql;
        }

        // Convert named placeholders (e.g., :id, :name) to positional placeholders (?)
        $positionalSql = preg_replace('/:\w+/', '?', $sql);
        
        // Extract parameter values in the order they appear in the query
        $values = [];
        $paramKeys = array_keys($params);
        
        // Parse the SQL to find placeholder order
        preg_match_all('/:\w+/', $sql, $matches);
        foreach ($matches[0] as $placeholder) {
            $key = ltrim($placeholder, ':');
            if (isset($params[$key])) {
                $values[] = $params[$key];
            }
        }

        // Use WordPress's prepare() - it expects values in the same order as ?
        return $this->wpdb->prepare($positionalSql, ...$values);
    }
}