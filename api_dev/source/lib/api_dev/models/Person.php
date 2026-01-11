<?php

namespace ApiDev\Models;

use ApiDev\Mysql\ModelConnection;
use ApiDev\Mysql\Configuration;

class Person
{

    /**
     * Returns all rows from the 'persons' table.
     *
     * @return array
     */
    private $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public static function all(): array
    {
        $rows = self::getConnection()->list();
        return array_map(function ($attrs) {
            return new self($attrs);
        }, $rows);
    }
    
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
}
