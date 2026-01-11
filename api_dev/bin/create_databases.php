#!/usr/bin/env php

<?php
require_once __DIR__ . '/../source/lib/mysql/Connection.php';
require_once __DIR__ . '/../source/lib/mysql/Configuration.php';
require_once __DIR__ . '/../source/lib/mysql/DatabaseInitializer.php';

class DatabaseEnsurer {
    private $database;

    public function __construct($database) {
        $this->database = $database;
    }

    public function ensure() {
        $initializer = new \ApiDev\Mysql\DatabaseInitializer($this->connection());

        $initializer->initialize();

        echo "Database '$this->database' ensured!\n";
    }

    private function getHost() {
        return getenv('API_DEV_MYSQL_HOST') ?: 'localhost';
    }
    private function getUser() {
        return getenv('API_DEV_MYSQL_USER') ?: 'root';
    }
    private function getPassword() {
        return getenv('API_DEV_MYSQL_PASSWORD') ?: '';
    }
    private function getPort() {
        return getenv('API_DEV_MYSQL_PORT') ?: 3306;
    }
    private function getDatabase() {
        return $this->database;
    }

    private function connection() {
        $host = $this->getHost();
        $user = $this->getUser();
        $password = $this->getPassword();
        $port = $this->getPort();
        $database = $this->getDatabase();

        \ApiDev\Mysql\Configuration::ensureDatabaseExists(
            $host, $user, $password, $port, $database
        );

        $configuration = new \ApiDev\Mysql\Configuration(
            $host, $database, $user, $password, $port
        );

        return $configuration->getConnection();
    }
}

$databases = [
    getenv('API_DEV_MYSQL_TEST_DATABASE') ?: 'api_tent_test_db',
    getenv('API_DEV_MYSQL_TEST_DATABASE_2') ?: 'api_tent_dev_db'
];
foreach ($databases as $database) {
    (new DatabaseEnsurer($database))->ensure();
}