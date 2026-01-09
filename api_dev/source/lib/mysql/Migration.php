<?php

namespace ApiDev\Mysql;

use PDO;
use Exception;

class Migration
{
    private $connection;
    private $sqlFilePath;

    public function __construct(Connection $connection, string $sqlFilePath)
    {
        $this->connection = $connection;
        $this->sqlFilePath = $sqlFilePath;
    }

    /**
     * Runs the SQL statements from a file
     *
     * @param string $sqlFilePath
     * @throws Exception if file does not exist or execution fails
     */
    public function run(): void
    {
        $this->checkFileExistence();
        $sql = file_get_contents($this->sqlFilePath);
        if ($sql === false) {
            throw new Exception("Failed to read SQL file: {$this->sqlFilePath}");
        }
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $statement) {
            if ($statement !== '') {
                $this->connection->execute($statement);
            }
        }
    }

    private function checkFileExistence(): void
    {
        if (!file_exists($this->sqlFilePath)) {
            throw new Exception("SQL file not found: {$this->sqlFilePath}");
        }
    }
}
