<?php

namespace ApiDev\Models;

use ApiDev\Mysql\ModelConnection;
use ApiDev\Mysql\Configuration;
use ApiDev\Exceptions\InvalidModelException;

/**
 * Abstract base class for database models.
 *
 * Provides common functionality for interacting with database tables including
 * querying, saving, validating, and serializing model data. Subclasses must
 * implement table-specific logic such as table name and attribute definitions.
 */
abstract class BaseModel
{
    /**
     * @var ModelConnection|null Shared database connection for the model's table
     */
    protected static $connection = null;

    /**
     * @var array The model's attribute data (column => value)
     */
    protected array $attributes = [];

    /**
     * Returns the database table name for this model.
     *
     * @return string The table name
     */
    abstract public static function tableName(): string;

    /**
     * Returns all rows from the table as an array of model instances.
     *
     * @return array Array of model instances
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
     * Creates and caches a connection on first call.
     *
     * @return ModelConnection The database connection for this model
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
     * Creates a new model instance.
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
     * @return int|null The model ID
     */
    public function getId(): ?int
    {
        return $this->attributes['id'] ?? null;
    }

    /**
     * Returns the model's attributes as an associative array.
     *
     * @return array The model attributes
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Returns the list of valid attribute names for the model.
     *
     * @return array The attribute names
     */
    abstract protected static function attributeNames(): array;

    /**
     * Checks if the model's attributes are valid.
     *
     * Must be implemented by subclasses to define validation rules.
     *
     * @return bool True if valid, false otherwise
     */
    abstract public function valid(): bool;

    /**
     * Validates the model's attributes and throws an exception if invalid.
     *
     * @return void
     * @throws InvalidModelException If validation fails
     */
    public function validate(): void
    {
        if (!$this->valid()) {
            throw new InvalidModelException('Invalid model attributes');
        }
    }

    /**
     * Saves the model to the database.
     *
     * Inserts a new record if the model doesn't have an ID, otherwise updates
     * the existing record. Validates the model before saving.
     *
     * @return void
     * @throws InvalidModelException If validation fails
     */
    public function save(): void
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
     * Returns the model's attributes as a JSON-ready array.
     *
     * Includes only the attributes defined by attributeNames(),
     * setting missing attributes to null.
     *
     * @return array Associative array of attributes
     */
    public function asJson(): array
    {
        $attributes = [];
        static::attributeNames();
        foreach (static::attributeNames() as $key) {
            $attributes[$key] = $this->attributes[$key] ?? null;
        }
        return $attributes;
    }

    /**
     * Returns the model's attributes as a JSON string.
     *
     * @return string JSON-encoded string of attributes
     */
    public function toJson(): string
    {
        return json_encode($this->asJson());
    }
}
