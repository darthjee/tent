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
    public static function getConnection(): ModelConnection
    {
        return new ModelConnection(
            Configuration::connect(),
            'persons'
        );
    }
}
