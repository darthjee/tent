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

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }
}
