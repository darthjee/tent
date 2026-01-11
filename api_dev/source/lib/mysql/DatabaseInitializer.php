<?php

namespace ApiDev\Mysql;

use Exception;

class DatabaseInitializer
{
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Ensures the database exists and creates the migrations table if needed.
     *
     * @throws Exception
     */
    public function initialize(): void
    {
        // Create migrations table if it does not exist
        $this->connection->execute(
            "CREATE TABLE IF NOT EXISTS migrations (
                name VARCHAR(255) PRIMARY KEY
            )"
        );
    }
}
