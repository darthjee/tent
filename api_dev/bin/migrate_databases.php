#!/usr/bin/env php

<?php
require_once __DIR__ . '/../source/lib/mysql/Connection.php';
require_once __DIR__ . '/../source/lib/mysql/Configuration.php';
require_once __DIR__ . '/../source/lib/mysql/MigrationsProcessor.php';
require_once __DIR__ . '/../source/lib/mysql/Migration.php';

class DatabaseMigrater {
    private $database;

    public function __construct($database) {
        $this->database = $database;
    }

    public function migrate() {
        \ApiDev\Mysql\MigrationsProcessor::migrate($this->connection());

        echo "Database '$this->database' migrated!\n";
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

        $configuration = new \ApiDev\Mysql\Configuration(
            $host, $database, $user, $password, $port
        );

        return $configuration->getConnection();
    }
}

$databases = [
    getenv('API_DEV_MYSQL_TEST_DATABASE') ?: 'api_tent_test_db',
    getenv('API_DEV_MYSQL_DEV_DATABASE') ?: null,
];

foreach ($databases as $database) {
    if ($database === null) {
        continue;
    }
    (new DatabaseMigrater($database))->migrate();
}