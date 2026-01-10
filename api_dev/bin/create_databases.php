#!/usr/bin/env php

<?php
require_once __DIR__ . '/../source/lib/mysql/Connection.php';
require_once __DIR__ . '/../source/lib/mysql/Configuration.php';

class DatabaseEnsurer {
    public function getHost() {
        return getenv('API_DEV_MYSQL_HOST') ?: 'localhost';
    }
    public function getUser() {
        return getenv('API_DEV_MYSQL_USER') ?: 'root';
    }
    public function getPassword() {
        return getenv('API_DEV_MYSQL_PASSWORD') ?: '';
    }
    public function getPort() {
        return getenv('API_DEV_MYSQL_PORT') ?: 3306;
    }
    public function getDatabase() {
        return getenv('API_DEV_MYSQL_TEST_DATABASE') ?: 'test_db';
    }

    public function ensure() {
        $host = $this->getHost();
        $user = $this->getUser();
        $password = $this->getPassword();
        $port = $this->getPort();
        $database = $this->getDatabase();

        \ApiDev\Mysql\Configuration::ensureDatabaseExists(
            $host, $user, $password, $port, $database
        );

        echo "Database '$database' ensured!\n";
    }
}

(new DatabaseEnsurer())->ensure();
