<?php

namespace ApiDev\Tests;

require_once __DIR__ . '/../../../support/tests_loader.php';

use PHPUnit\Framework\TestCase;
use ApiDev\Mysql\Connection;
use ApiDev\Mysql\ModelConnection;

class ModelConnectionInsertTest extends TestCase
{
    private $connection;
    private $model;
    private $database;

    protected function setUp(): void
    {
        $host = getenv('API_DEV_MYSQL_HOST') ?: 'localhost';
        $user = getenv('API_DEV_MYSQL_USER') ?: 'root';
        $password = getenv('API_DEV_MYSQL_PASSWORD') ?: '';
        $port = getenv('API_DEV_MYSQL_PORT') ?: 3306;
        $this->database = getenv('API_DEV_MYSQL_TEST_DATABASE') ?: 'api_tent_test_db';

        $this->connection = Connection::build($host, $port, $this->database, $user, $password);
        $this->model = new ModelConnection($this->connection, 'persons');
    }

    public function testInsertPerson()
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'birthdate' => '1990-01-01'
        ];
        $id = $this->model->insert($data);
        $this->assertIsNumeric($id);

        $row = $this->connection->fetch('SELECT * FROM persons WHERE id = ?', [$id]);
        $this->assertNotEmpty($row);
        $this->assertEquals('John', $row['first_name']);
        $this->assertEquals('Doe', $row['last_name']);
        $this->assertEquals('1990-01-01', $row['birthdate']);
    }
}
