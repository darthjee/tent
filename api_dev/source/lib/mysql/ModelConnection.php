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
        return $this->connection->fetch($sql, $params);
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
