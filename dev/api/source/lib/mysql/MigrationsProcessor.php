<?php

namespace ApiDev\Mysql;

use Exception;

/**
 * Processes and executes database migrations from a directory.
 *
 * Scans a directory for SQL migration files and executes them in order.
 * Provides a static method for running migrations from the default location.
 */
class MigrationsProcessor
{
    /**
     * Default directory path for migrations
     */
    private const MIGRATIONS_DIR = './migrations';

    /**
     * @var string The directory path containing migration files
     */
    private string $directoryPath;

    /**
     * @var Connection The database connection
     */
    private Connection $connection;

    /**
     * Creates a new MigrationsProcessor instance.
     *
     * @param string $directoryPath The directory containing migration files
     * @param Connection $connection The database connection
     */
    public function __construct(string $directoryPath, Connection $connection)
    {
        $this->directoryPath = $directoryPath;
        $this->connection = $connection;
    }

    /**
     * Runs all migrations from the default directory.
     *
     * Static convenience method for running migrations from the default
     * './migrations' directory using the given connection.
     *
     * @param Connection $connection The database connection
     * @return void
     * @throws Exception If directory is invalid or migration fails
     */
    public static function migrate(Connection $connection): void
    {
        $processor = new self(self::MIGRATIONS_DIR, $connection);
        $processor->run();
    }

    /**
     * Runs all SQL migrations in the directory, ordered by filename.
     *
     * Finds all .sql files in the directory, sorts them alphabetically,
     * and executes each one using a Migration instance.
     *
     * @return void
     * @throws Exception If directory is invalid or migration fails
     */
    public function run(): void
    {
        if (!is_dir($this->directoryPath)) {
            throw new Exception("Migrations directory not found: {$this->directoryPath}");
        }
        $files = glob(rtrim($this->directoryPath, '/\\') . '/*.sql');
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            $migration = new Migration($this->connection, $file);
            $migration->run();
        }
    }
}
