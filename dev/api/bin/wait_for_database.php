<?php

namespace ApiDev\Bin;

require_once __DIR__ . '/../source/lib/mysql/Connection.php';
require_once __DIR__ . '/../source/lib/mysql/Configuration.php';

class DatabaseWaiter
{
    public static function getHost()
    {
        return getenv('API_DEV_MYSQL_HOST') ?: 'localhost';
    }
    public static function getUser()
    {
        return getenv('API_DEV_MYSQL_USER') ?: 'root';
    }
    public static function getPassword()
    {
        return getenv('API_DEV_MYSQL_PASSWORD') ?: '';
    }
    public static function getPort()
    {
        return getenv('API_DEV_MYSQL_PORT') ?: 3306;
    }
    public static function getDatabase()
    {
        return getenv('API_DEV_MYSQL_TEST_DATABASE') ?: 'test_db';
    }
    public static function databaseFound()
    {
        $host = self::getHost();
        $user = self::getUser();
        $password = self::getPassword();
        $port = self::getPort();
        $database = self::getDatabase();
        try {
            return \ApiDev\Mysql\Configuration::databaseExists($host, $user, $password, $port, $database);
        } catch (\PDOException $e) {
            // If connection fails, consider database as not found
            return false;
        }
    }
    public static function wait()
    {
        $database = self::getDatabase();
        $maxTries = 10;

        for ($i = 0; $i < $maxTries; $i++) {
            if (self::databaseFound()) {
                return self::success();
            }
            echo "Waiting for database '$database'...\n";
            sleep(1);
        }
        echo "Database '$database' not found after $maxTries attempts.\n";
    }

    private static function success()
    {
        $database = self::getDatabase();
        echo "Database '" . $database . "' ensured!\n";
    }
}

DatabaseWaiter::wait();
