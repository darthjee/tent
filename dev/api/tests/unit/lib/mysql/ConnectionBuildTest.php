<?php

namespace ApiDev\Tests;

use PHPUnit\Framework\TestCase;
use ApiDev\Mysql\Connection;

class ConnectionBuildTest extends TestCase
{
    public function testBuildCreatesValidConnection()
    {
        $host = getenv('API_DEV_MYSQL_HOST') ?: 'localhost';
        $user = getenv('API_DEV_MYSQL_USER') ?: 'root';
        $password = getenv('API_DEV_MYSQL_PASSWORD') ?: '';
        $port = getenv('API_DEV_MYSQL_PORT') ?: 3306;
        $database = getenv('API_DEV_MYSQL_TEST_DATABASE') ?: 'api_tent_test_db';

        $connection = Connection::build($host, $port, $database, $user, $password);
        $this->assertInstanceOf(Connection::class, $connection);
        $this->assertInstanceOf(\PDO::class, $connection->getPdo());

        // Simple query to check connection
        $result = $connection->fetch('SELECT DATABASE() as db');
        $this->assertEquals($database, $result['db']);
    }
}
