<?php

namespace ApiDev\Mysql;

use PDO;
use Exception;

/**
 * Represents a single database migration.
 *
 * Handles execution of SQL migration files, tracking whether they've been applied,
 * and recording their execution in the migrations table.
 */
class Migration
{
    /**
     * @var Connection The database connection
     */
    private Connection $connection;

    /**
     * @var string The file path to the SQL migration file
     */
    private string $sqlFilePath;

    /**
     * @var string|null Cached content of the SQL file
     */
    private ?string $fileContent = null;

    /**
     * Creates a new Migration instance.
     *
     * @param Connection $connection The database connection
     * @param string $sqlFilePath The path to the SQL migration file
     */
    public function __construct(Connection $connection, string $sqlFilePath)
    {
        $this->connection = $connection;
        $this->sqlFilePath = $sqlFilePath;
    }

    /**
     * Runs the migration if it hasn't been applied yet.
     *
     * Checks if the migration has already been executed, and if not,
     * executes the SQL statements and records the migration.
     *
     * @return void
     * @throws Exception If file doesn't exist or execution fails
     */
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
     * @return bool True if the migration has been applied, false otherwise
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

    /**
     * Records that this migration has been applied.
     *
     * @return void
     */
    private function recordMigration(): void
    {
        // Insert migration record (just the filename, not full path)
        $filename = $this->fileName();
        $this->connection->execute(
            "INSERT INTO migrations (name) VALUES (?)",
            [$filename]
        );
    }

    /**
     * Executes the SQL statements in the migration file.
     *
     * @return void
     * @throws Exception If file doesn't exist or execution fails
     */
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

    /**
     * Returns the content of the SQL file.
     *
     * @return string The SQL file content
     * @throws Exception If reading the file fails
     */
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

    /**
     * Checks if the SQL file exists.
     *
     * @return void
     * @throws Exception If the file doesn't exist
     */
    private function checkFileExistence(): void
    {
        if (!file_exists($this->sqlFilePath)) {
            throw new Exception("SQL file not found: {$this->sqlFilePath}");
        }
    }

    /**
     * Returns the filename (without path) of the migration.
     *
     * @return string The migration filename
     */
    private function fileName(): string
    {
        return basename($this->sqlFilePath);
    }
}
