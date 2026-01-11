<?php

namespace ApiDev\Mysql;

require_once __DIR__ . '/Connection.php';

use ApiDev\Mysql\Connection;

class Configuration
{
    private static $instance;
    private $host;
    private $database;
    private $username;
    private $password;
    private $port;

    private $connection;

    public static function getInstance()
    {
        return self::$instance;
    }

    public static function configure($host, $database, $username, $password, $port = 3306)
    {
        self::$instance = new Configuration($host, $database, $username, $password, $port);
        return self::$instance;
    }

    public static function connect()
    {
        $config = self::getInstance();
        return $config->getConnection();
    }

    public function getConnection()
    {
        if ($this->connection === null) {
            $this->connection = Connection::build(
                $this->host,
                $this->port,
                $this->database,
                $this->username,
                $this->password
            );
        }
        return $this->connection;
    }


    public static function connectWithoutDatabase($host, $username, $password, $port = 3306)
    {
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pdo = new \PDO($dsn, $username, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return new \ApiDev\Mysql\Connection($pdo);
    }

    public static function createDatabase($host, $username, $password, $port, $databaseName)
    {
        $conn = self::connectWithoutDatabase($host, $username, $password, $port);
        $command = "CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        $conn->getPdo()->exec($command);
    }

    public static function databaseExists($host, $username, $password, $port, $databaseName)
    {
        $conn = self::connectWithoutDatabase($host, $username, $password, $port);
        $command = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$databaseName}'";
        $stmt = $conn->getPdo()->query($command);
        return (bool) $stmt->fetchColumn();
    }

    public static function ensureDatabaseExists($host, $username, $password, $port, $databaseName)
    {
        if (!self::databaseExists($host, $username, $password, $port, $databaseName)) {
            self::createDatabase($host, $username, $password, $port, $databaseName);
        }
    }

    public function __construct($host, $database, $username, $password, $port = 3306)
    {
        $this->host = $host;
        $this->database = $database;
        $this->username = $username;
        $this->password = $password;
        $this->port = $port;
    }

    public function getHost()
    {
        return $this->host;
    }

    public function getDatabase()
    {
        return $this->database;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function getPort()
    {
        return $this->port;
    }
}
