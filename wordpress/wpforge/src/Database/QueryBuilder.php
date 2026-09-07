<?php

namespace WPForge\Database;

/**
 * Safe SQL query builder for read-only operations.
 */
class QueryBuilder
{
    /** @var \wpdb */
    private $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Build and execute a SELECT query from parts.
     */
    public function select(array $params): array
    {
        $table   = $this->wpdb->escape($params['table'] ?? '');
        $columns = $params['columns'] ?? ['*'];
        $where   = $params['where'] ?? [];
        $orderBy = $params['order_by'] ?? '';
        $limit   = (int) ($params['limit'] ?? 20);
        $offset  = (int) ($params['offset'] ?? 0);

        $colSql = implode(', ', array_map([$this->wpdb, 'escape'], $columns));
        $sql = "SELECT {$colSql} FROM `{$table}`";

        $whereClauses = [];
        $values = [];

        foreach ($where as $column => $value) {
            $safeCol = $this->wpdb->escape($column);
            if (is_array($value)) {
                $op = $value['op'] ?? '=';
                $val = $value['value'] ?? null;
                $safeVal = $this->wpdb->prepare('%s', $val);
                $whereClauses[] = "`{$safeCol}` {$op} {$safeVal}";
            } else {
                $safeVal = $this->wpdb->prepare('%s', $value);
                $whereClauses[] = "`{$safeCol}` = {$safeVal}";
            }
        }

        if (!empty($whereClauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
        }

        if ($orderBy) {
            $parts = explode(' ', $orderBy);
            $safeOrder = $this->wpdb->escape($parts[0]);
            $dir = isset($parts[1]) && strtoupper($parts[1]) === 'DESC' ? 'DESC' : 'ASC';
            $sql .= " ORDER BY `{$safeOrder}` {$dir}";
        }

        $sql .= $this->wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $offset);

        $result = $this->wpdb->get_results($sql, ARRAY_A);

        // Count total
        $countSql = "SELECT COUNT(*) FROM `{$table}`";
        if (!empty($whereClauses)) {
            $countSql .= ' WHERE ' . implode(' AND ', $whereClauses);
        }
        $total = (int) $this->wpdb->get_var($countSql);

        return [
            'success' => true,
            'rows'    => count($result ?: []),
            'data'    => $result ?: [],
            'total'   => $total,
            'query'   => $sql,
        ];
    }

    /**
     * Escape a table or column name.
     */
    public function escape(string $identifier): string
    {
        return $this->wpdb->escape($identifier);
    }
}
