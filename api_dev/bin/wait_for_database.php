#!/usr/bin/env php
<?php
require_once __DIR__ . '/../source/lib/mysql/Connection.php';
require_once __DIR__ . '/../source/lib/mysql/Configuration.php';

class DatabaseWaiter {
    public static function wait($host, $user, $password, $port, $database) {
        while (!\ApiDev\Mysql\Configuration::databaseExists($host, $user, $password, $port, $database)) {
            echo "Waiting for database '$database'...\n";
            sleep(1);
        }
    }
}

$host = getenv('API_DEV_MYSQL_HOST') ?: 'localhost';
$user = getenv('API_DEV_MYSQL_USER') ?: 'root';
$password = getenv('API_DEV_MYSQL_PASSWORD') ?: '';
$port = getenv('API_DEV_MYSQL_PORT') ?: 3306;
$database = getenv('API_DEV_MYSQL_TEST_DATABASE') ?: 'test_db';

DatabaseWaiter::wait($host, $user, $password, $port, $database);

echo "Database '$database' ensured!\n";
