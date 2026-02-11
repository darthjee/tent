<?php

namespace ApiDev\Models;

use ApiDev\Mysql\ModelConnection;
use ApiDev\Mysql\Configuration;
use ApiDev\Exceptions\InvalidModelException;

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
    protected array $attributes = [];

    abstract public static function tableName(): string;

    /**
     * Returns all rows from the table as an array of model instances.
     *
     * @return array
     */
    public static function all(): array
    {
        $rows = static::getConnection()->list();
        return array_map(function ($attrs) {
            return new static($attrs);
        }, $rows);
    }

    /**
     * Returns a ModelConnection instance for the model's table.
     *
     * @return ModelConnection
     */
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

    /**
     * BaseModel constructor.
     *
     * @param array $attributes Associative array of column => value
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * Returns the ID of the model, or null if not set.
     *
     * @return int|null
     */
    public function getId()
    {
        return $this->attributes['id'] ?? null;
    }

    /**
     * Returns the model's attributes as an associative array.
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Checks if the model's attributes are valid. Must be implemented by subclasses.
     */
    abstract public function valid(): bool;

    /**
     * Validates the model's attributes and throws an exception if invalid.
     *
     * @throws InvalidModelException
     */
    public function validate()
    {
        if (!$this->valid()) {
            throw new InvalidModelException('Invalid model attributes');
        }
    }

    /**
     * Saves a record to the database
     */
    public function save()
    {
        $this->validate();
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

    /**
     * Converts camelCase to snake_case
     */
    private function camelToSnake(string $input): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $input));
    }

    /**
     * Returns the model's attributes as a JSON string with snake_case keys.
     *
     * @return array
     */
    public function asJson(): array
    {
        $snakeCaseAttributes = [];
        foreach ($this->getAttributes() as $key => $value) {
            $snakeCaseKey = $this->camelToSnake($key);
            $snakeCaseAttributes[$snakeCaseKey] = $value;
        }
        return $snakeCaseAttributes;
    }

    public function toJson(): string
    {
        return json_encode($this->asJson());
    }
}
