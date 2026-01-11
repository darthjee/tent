<?php

namespace ApiDev\Tests;

require_once __DIR__ . '/../../../../source/lib/mysql/MigrationsProcessor.php';

use PHPUnit\Framework\TestCase;
use ApiDev\Mysql\MigrationsProcessor;
use ApiDev\Mysql\Connection;

class MigrationsProcessorTest extends TestCase
{
    private $connection;
    private $migrationsDir;
    private $files;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->migrationsDir = sys_get_temp_dir() . '/migrations_' . uniqid();
        mkdir($this->migrationsDir);
        $this->files = [
            $this->migrationsDir . '/001_create_table.sql',
            $this->migrationsDir . '/002_insert_data.sql',
        ];
        file_put_contents($this->files[0], 'CREATE TABLE test (id INT);');
        file_put_contents($this->files[1], 'INSERT INTO test (id) VALUES (1);');
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->migrationsDir)) {
            rmdir($this->migrationsDir);
        }
    }

    public function testRunExecutesAllMigrationsInOrder()
    {
        $filename1 = basename($this->files[0]);
        $filename2 = basename($this->files[1]);
        $this->connection->expects($this->exactly(4))
            ->method('execute')
            ->withConsecutive(
                [$this->stringContains('CREATE TABLE test (id INT)')],
                [
                    $this->stringContains('INSERT INTO migrations (name) VALUES (?)'),
                    [$filename1]
                ],
                [$this->stringContains('INSERT INTO test (id) VALUES (1)')],
                [
                    $this->stringContains('INSERT INTO migrations (name) VALUES (?)'),
                    [$filename2]
                ]
            );

        $processor = new MigrationsProcessor($this->migrationsDir, $this->connection);
        $processor->run();
    }
}
