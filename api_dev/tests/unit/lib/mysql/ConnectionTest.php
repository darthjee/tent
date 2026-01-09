<?php

namespace ApiDev\Tests;

use PHPUnit\Framework\TestCase;
use ApiDev\Mysql\Configuration;
use ApiDev\Mysql\Connection;

require_once __DIR__ . '/../../../../source/lib/mysql/Configuration.php';
require_once __DIR__ . '/../../../../source/lib/mysql/Connection.php';

class ConnectionTest extends TestCase
{
    private $connection;

    protected function setUp(): void
    {
        $host = getenv('API_DEV_MYSQL_HOST') ?: 'localhost';
        $user = getenv('API_DEV_MYSQL_USER') ?: 'root';
        $password = getenv('API_DEV_MYSQL_PASSWORD') ?: '';
        $port = getenv('API_DEV_MYSQL_PORT') ?: 3306;
        $database = 'test_db';

        Configuration::configure($host, $database, $user, $password, $port);
        $this->connection = Configuration::connect();

        // Create test table
        $this->connection->execute("DROP TABLE IF EXISTS test_users");
        $this->connection->execute("
            CREATE TABLE test_users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100),
                email VARCHAR(100)
            )
        ");
    }

    protected function tearDown(): void
    {
        $this->connection->execute("DROP TABLE IF EXISTS test_users");
    }

    public function testExecuteInsertsData()
    {
        $rowCount = $this->connection->execute(
            "INSERT INTO test_users (name, email) VALUES (:name, :email)",
            ['name' => 'John Doe', 'email' => 'john@example.com']
        );

        $this->assertEquals(1, $rowCount);
    }

    public function testLastInsertId()
    {
        $this->connection->execute(
            "INSERT INTO test_users (name, email) VALUES (:name, :email)",
            ['name' => 'Jane Doe', 'email' => 'jane@example.com']
        );

        $id = $this->connection->lastInsertId();
        $this->assertGreaterThan(0, $id);
    }

    public function testFetchReturnsOneRow()
    {
        $this->connection->execute(
            "INSERT INTO test_users (name, email) VALUES (:name, :email)",
            ['name' => 'Alice', 'email' => 'alice@example.com']
        );

        $user = $this->connection->fetch(
            "SELECT * FROM test_users WHERE name = :name",
            ['name' => 'Alice']
        );

        $this->assertIsArray($user);
        $this->assertEquals('Alice', $user['name']);
        $this->assertEquals('alice@example.com', $user['email']);
    }

    public function testFetchAllReturnsMultipleRows()
    {
        $this->connection->execute(
            "INSERT INTO test_users (name, email) VALUES ('Bob', 'bob@example.com')"
        );
        $this->connection->execute(
            "INSERT INTO test_users (name, email) VALUES ('Charlie', 'charlie@example.com')"
        );

        $users = $this->connection->fetchAll("SELECT * FROM test_users");

        $this->assertIsArray($users);
        $this->assertCount(2, $users);
        $this->assertEquals('Bob', $users[0]['name']);
        $this->assertEquals('Charlie', $users[1]['name']);
    }

    public function testQueryReturnsStatement()
    {
        $this->connection->execute(
            "INSERT INTO test_users (name, email) VALUES ('David', 'david@example.com')"
        );

        $stmt = $this->connection->query("SELECT * FROM test_users WHERE name = :name", ['name' => 'David']);

        $this->assertInstanceOf(\PDOStatement::class, $stmt);
        $user = $stmt->fetch();
        $this->assertEquals('David', $user['name']);
    }

    public function testGetPdoReturnsPdoInstance()
    {
        $pdo = $this->connection->getPdo();
        $this->assertInstanceOf(\PDO::class, $pdo);
    }

    public function testExecuteUpdate()
    {
        $this->connection->execute(
            "INSERT INTO test_users (name, email) VALUES ('Eve', 'eve@example.com')"
        );

        $rowCount = $this->connection->execute(
            "UPDATE test_users SET email = :email WHERE name = :name",
            ['email' => 'eve.new@example.com', 'name' => 'Eve']
        );

        $this->assertEquals(1, $rowCount);

        $user = $this->connection->fetch("SELECT * FROM test_users WHERE name = 'Eve'");
        $this->assertEquals('eve.new@example.com', $user['email']);
    }

    public function testExecuteDelete()
    {
        $this->connection->execute(
            "INSERT INTO test_users (name, email) VALUES ('Frank', 'frank@example.com')"
        );

        $rowCount = $this->connection->execute(
            "DELETE FROM test_users WHERE name = :name",
            ['name' => 'Frank']
        );

        $this->assertEquals(1, $rowCount);

        $users = $this->connection->fetchAll("SELECT * FROM test_users");
        $this->assertCount(0, $users);
    }
}
