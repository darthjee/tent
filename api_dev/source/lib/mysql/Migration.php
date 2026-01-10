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
     * @throws Exception if file does not exist or execution fails
     */
    private $fileContent = null;

    public function run(): void
    {
        $this->checkFileExistence();
        $sql = $this->fileContent();
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $statement) {
            if ($statement !== '') {
                $this->connection->execute($statement);
            }
        }
    }

    private function fileContent(): string
    {
        if ($this->fileContent !== null) {
            return $this->fileContent;
        }
        $content = file_get_contents($this->sqlFilePath);
        if ($content === false) {
            throw new Exception("Failed to read SQL file: {$this->sqlFilePath}");
        }
        $this->fileContent = $content;
        return $content;
    }

    private function checkFileExistence(): void
    {
        if (!file_exists($this->sqlFilePath)) {
            throw new Exception("SQL file not found: {$this->sqlFilePath}");
        }
    }
}
