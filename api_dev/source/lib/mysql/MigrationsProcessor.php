<?php

namespace ApiDev\Mysql;

use Exception;

class MigrationsProcessor
{
    private $directoryPath;
    private $connection;

    public function __construct(string $directoryPath, Connection $connection)
    {
        $this->directoryPath = $directoryPath;
        $this->connection = $connection;
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
