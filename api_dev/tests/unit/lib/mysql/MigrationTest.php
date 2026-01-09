<?php

require_once __DIR__ . '/../../../../source/lib/mysql/Migration.php';

use PHPUnit\Framework\TestCase;
use ApiDev\Mysql\Migration;
use ApiDev\Mysql\Connection;

class MigrationTest extends TestCase
{
    private $connection;
    private $sqlFile;

    protected function setUp(): void
    {
        // Setup a mock Connection
        $this->connection = $this->createMock(Connection::class);
        // Create a temporary SQL file
        $this->sqlFile = tempnam(sys_get_temp_dir(), 'sql');
        file_put_contents($this->sqlFile, "CREATE TABLE test_table (id INT);\nINSERT INTO test_table (id) VALUES (1);");
    }

    protected function tearDown(): void
    {
        if (file_exists($this->sqlFile)) {
            unlink($this->sqlFile);
        }
    }

    public function testRunExecutesAllStatements()
    {
        $this->connection->expects($this->exactly(2))
            ->method('execute')
            ->withConsecutive(
                [$this->stringContains('CREATE TABLE test_table (id INT)')],
                [$this->stringContains('INSERT INTO test_table (id) VALUES (1)')]
            );

        $migration = new Migration($this->connection, $this->sqlFile);
        $migration->run();
    }

    public function testThrowsExceptionIfFileMissing()
    {
        $migration = new Migration($this->connection, '/non/existent/file.sql');
        $this->expectException(Exception::class);
        $migration->run();
    }

    public function testThrowsExceptionIfFileUnreadable()
    {
        $file = tempnam(sys_get_temp_dir(), 'sql');
        unlink($file); // Remove file to simulate unreadable
        $migration = new Migration($this->connection, $file);
        $this->expectException(Exception::class);
        $migration->run();
    }
}
