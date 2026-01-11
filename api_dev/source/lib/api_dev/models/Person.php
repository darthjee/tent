<?php

namespace ApiDev\Models;

require_once __DIR__ . '/../../mysql/ModelConnection.php';

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
    public function getId()
    {
        return $this->attributes['id'] ?? null;
    }

    public function getFirstName()
    {
        return $this->attributes['first_name'] ?? null;
    }

    public function getLastName()
    {
        return $this->attributes['last_name'] ?? null;
    }

    public function getBirthdate()
    {
        return $this->attributes['birthdate'] ?? null;
    }

    public function getCreatedAt()
    {
        return $this->attributes['created_at'] ?? null;
    }

    public function getUpdatedAt()
    {
        return $this->attributes['updated_at'] ?? null;
    }

    public function getAttributes()
    {
        return $this->attributes;
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
