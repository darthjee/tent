<?php

namespace ApiDev\Models;

use ApiDev\Mysql\ModelConnection;
use ApiDev\Mysql\Configuration;

abstract class BaseModel
{
    /**
     * Returns a ModelConnection for the table.
     *
     * @return ModelConnection
     */
    protected static $connection = null;

    /**
     * Returns all rows from the 'persons' table.
     *
     * @return array
     */
    protected $attributes = [];

    abstract public static function tableName(): string;

    public static function all(): array
    {
        $rows = static::getConnection()->list();
        return array_map(function ($attrs) {
            return new static($attrs);
        }, $rows);
    }

    public static function getConnection(): ModelConnection
    {
        if (static::$connection instanceof ModelConnection) {
            return static::$connection;
        }
        static::$connection = new ModelConnection(
            Configuration::connect(),
            static::tableName()
        );
        return static::$connection;
    }

    public function save()
    {
        $connection = static::getConnection();
        if ($this->getId() === null) {
            // Insert new record
            $id = $connection->insert($this->attributes);
            $this->attributes['id'] = $id;
        } else {
            // Update existing record
            $connection->update($this->getId(), $this->attributes);
        }
    }
}
