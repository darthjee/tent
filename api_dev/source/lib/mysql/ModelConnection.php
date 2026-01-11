<?php

namespace ApiDev\Mysql;

class ModelConnection
{
    private $connection;
    private $tableName;

    public function __construct(Connection $connection, string $tableName)
    {
        $this->connection = $connection;
        $this->tableName = $tableName;
    }

    /**
     * Lists rows from the table with optional limit and offset.
     *
     * @param array $options ['limit' => int, 'offset' => int]
     * @return array
     */
    public function list(array $options = []): array
    {
        $sql = "SELECT * FROM {$this->tableName}";
        $params = [];
        if (isset($options['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$options['limit'];
        }
        if (isset($options['offset'])) {
            // If limit is not set, MySQL requires LIMIT for OFFSET
            if (!isset($options['limit'])) {
                $sql .= " LIMIT 18446744073709551615"; // MySQL max value for LIMIT
            }
            $sql .= " OFFSET ?";
            $params[] = (int)$options['offset'];
        }
        return $this->connection->fetchAll($sql, $params);
    }

    /**
     * Inserts a row into the table.
     *
     * @param array $attributes Associative array of column => value
     * @return int Last insert ID
     */
    public function insert(array $attributes): int
    {
        $columns = array_keys($attributes);
        $placeholders = array_fill(0, count($columns), '?');
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->tableName,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        $this->connection->execute($sql, array_values($attributes));
        return $this->connection->lastInsertId();
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }
}
