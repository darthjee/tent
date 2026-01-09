#!/usr/bin/env php
<?php
require_once __DIR__ . '/../source/lib/mysql/Connection.php';
require_once __DIR__ . '/../source/lib/mysql/Configuration.php';

$host = getenv('API_DEV_MYSQL_HOST') ?: 'localhost';
$user = getenv('API_DEV_MYSQL_USER') ?: 'root';
$password = getenv('API_DEV_MYSQL_PASSWORD') ?: '';
$port = getenv('API_DEV_MYSQL_PORT') ?: 3306;
$database = getenv('API_DEV_MYSQL_TEST_DATABASE') ?: 'test_db';

while (!\ApiDev\Mysql\Configuration::databaseExists($host, $user, $password, $port, $database)) {
    echo "Waiting for database '$database'...\n";
    sleep(1);
}

echo "Database '$database' ensured!\n";
