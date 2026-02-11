<?php

namespace ApiDev\Mysql;

use Exception;

/**
 * Initializes database schema for the application.
 *
 * Sets up required database tables such as the migrations tracking table.
 */
class DatabaseInitializer
{
    /**
     * @var Connection The database connection
     */
    private Connection $connection;

    /**
     * Creates a new DatabaseInitializer instance.
     *
     * @param Connection $connection The database connection to use
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Initializes the database schema.
     *
     * Creates the migrations tracking table if it doesn't already exist.
     *
     * @return void
     * @throws Exception If initialization fails
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
