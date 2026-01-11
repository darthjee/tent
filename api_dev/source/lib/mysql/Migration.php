<?php

namespace ApiDev\Mysql;

use PDO;
use Exception;

class Migration
{
    private $connection;
    private $sqlFilePath;

    /**
     * Runs the SQL statements from a file
     *
     * @throws Exception if file does not exist or execution fails
     */
    private $fileContent = null;

    public function __construct(Connection $connection, string $sqlFilePath)
    {
        $this->connection = $connection;
        $this->sqlFilePath = $sqlFilePath;
    }

    public function run(): void
    {
        if (!$this->isMigrated()) {
            $this->execute();
            $this->recordMigration();
        }
    }

    /**
     * Checks if this migration has already been applied.
     *
     * @return bool
     */
    public function isMigrated(): bool
    {
        $filename = $this->fileName();
        $result = $this->connection->fetch(
            "SELECT 1 FROM migrations WHERE name = ? LIMIT 1",
            [$filename]
        );
        return !empty($result);
    }

    private function recordMigration(): void
    {
        // Insert migration record (just the filename, not full path)
        $filename = $this->fileName();
        $this->connection->execute(
            "INSERT INTO migrations (name) VALUES (?)",
            [$filename]
        );
    }

    private function execute(): void
    {
        echo "Executing migration: " . $this->fileName() . "\n";
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

    private function fileName(): string
    {
        return basename($this->sqlFilePath);
    }
}
