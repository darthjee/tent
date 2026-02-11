<?php

namespace ApiDev\Mysql;

/**
 * Database connection for model operations on a specific table.
 *
 * Provides high-level methods for common database operations (list, insert, update)
 * on a single table, abstracting away SQL query construction.
 */
class ModelConnection
{
    /**
     * @var Connection The underlying database connection
     */
    private Connection $connection;

    /**
     * @var string The table name for this model connection
     */
    private string $tableName;

    /**
     * Creates a new ModelConnection instance.
     *
     * @param Connection $connection The database connection
     * @param string $tableName The name of the table to operate on
     */
    public function __construct(Connection $connection, string $tableName)
    {
        $this->connection = $connection;
        $this->tableName = $tableName;
    }

    /**
     * Lists rows from the table with optional limit and offset.
     *
     * @param array $options Optional array with 'limit' and/or 'offset' keys
     * @return array Array of rows as associative arrays
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
     * @return int The ID of the inserted row (cast from string)
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
        return (int)$this->connection->lastInsertId();
    }

    /**
     * Updates a row in the table by ID.
     *
     * @param int $id The ID of the row to update
     * @param array $attributes Associative array of column => value to update
     * @return void
     */
    public function update(int $id, array $attributes): void
    {
        $setClauses = [];
        $params = [];
        foreach ($attributes as $column => $value) {
            $setClauses[] = "$column = ?";
            $params[] = $value;
        }
        $sql = sprintf(
            "UPDATE %s SET %s WHERE id = ?",
            $this->tableName,
            implode(', ', $setClauses)
        );
        $params[] = $id;
        $this->connection->execute($sql, $params);
    }

    /**
     * Returns the underlying database connection.
     *
     * @return Connection The database connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Returns the table name for this model connection.
     *
     * @return string The table name
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }
}
