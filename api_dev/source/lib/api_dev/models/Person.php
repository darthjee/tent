<?php

namespace ApiDev\Models;

use ApiDev\Mysql\ModelConnection;
use ApiDev\Mysql\Configuration;

class Person
{
    /**
     * Returns a ModelConnection for the 'persons' table.
     *
     * @return ModelConnection
     */
    private static $connection = null;

    public static function getConnection(): ModelConnection
    {
        if (self::$connection instanceof ModelConnection) {
            return self::$connection;
        }
        self::$connection = new ModelConnection(
            Configuration::connect(),
            'persons'
        );
        return self::$connection;
    }

    /**
     * Returns all rows from the 'persons' table.
     *
     * @return array
     */
    public static function all(): array
    {
        return self::getConnection()->list();
    }
}
