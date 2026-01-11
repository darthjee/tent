<?php

namespace ApiDev\Tests;

require_once __DIR__ . '/../../../../source/lib/mysql/Migration.php';

use PHPUnit\Framework\TestCase;
use ApiDev\Mysql\Migration;
use ApiDev\Mysql\Connection;

class MigrationTest extends TestCase
{
    private $connection;
    private $sqlFile;

    public function testIsMigratedReturnsTrueIfMigrationExists()
    {
        $filename = basename($this->sqlFile);
        $this->connection->method('fetch')
            ->willReturn([['name' => $filename]]);

        $migration = new Migration($this->connection, $this->sqlFile);
        $this->assertTrue($migration->isMigrated());
    }

    public function testIsMigratedReturnsFalseIfMigrationDoesNotExist()
    {
        $this->connection->method('fetch')
            ->willReturn([]);

        $migration = new Migration($this->connection, $this->sqlFile);
        $this->assertFalse($migration->isMigrated());
    }

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

    public function testRunExecutesAllStatementsAndInsertsMigration()
    {
        $filename = basename($this->sqlFile);
        $this->connection->expects($this->exactly(3))
            ->method('execute')
            ->withConsecutive(
                [$this->stringContains('CREATE TABLE test_table (id INT)')],
                [$this->stringContains('INSERT INTO test_table (id) VALUES (1)')],
                [
                    $this->stringContains('INSERT INTO migrations (name) VALUES (?)'),
                    [$filename]
                ]
            );

        $migration = new Migration($this->connection, $this->sqlFile);
        $migration->run();
    }

    public function testThrowsExceptionIfFileMissing()
    {
        $migration = new Migration($this->connection, '/non/existent/file.sql');
        $this->expectException(\Exception::class);
        $migration->run();
    }

    public function testThrowsExceptionIfFileUnreadable()
    {
        $file = tempnam(sys_get_temp_dir(), 'sql');
        unlink($file); // Remove file to simulate unreadable
        $migration = new Migration($this->connection, $file);
        $this->expectException(\Exception::class);
        $migration->run();
    }

    public function testRunDoesNotExecuteIfAlreadyMigrated()
    {
        $filename = basename($this->sqlFile);
        // Simulate migration already done
        $this->connection->method('fetch')
            ->willReturn([['name' => $filename]]);
        // Should not call execute at all
        $this->connection->expects($this->never())
            ->method('execute');

        $migration = new Migration($this->connection, $this->sqlFile);
        $migration->run();
    }
}
