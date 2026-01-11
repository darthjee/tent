<?php

namespace ApiDev\Mysql;

use Exception;

class MigrationsProcessor
{
    private const MIGRATIONS_DIR = './migrations';
    private $directoryPath;
    private $connection;

    public function __construct(string $directoryPath, Connection $connection)
    {
        $this->directoryPath = $directoryPath;
        $this->connection = $connection;
    }

    /**
     * Runs all migrations from the default './migrations' directory using the given connection.
     *
     * @param Connection $connection
     */
    public static function migrate(Connection $connection): void
    {
        $processor = new self(self::MIGRATIONS_DIR, $connection);
        $processor->run();
    }

    /**
     * Runs all .sql migrations in the directory, ordered by filename.
     *
     * @throws Exception if directory is invalid or migration fails
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
