<?php

namespace ApiDev\Models;

use ApiDev\Mysql\ModelConnection;
use ApiDev\Mysql\Configuration;
use ApiDev\Models\BaseModel;

/**
 * Represents a person record in the database.
 *
 * Provides access to person attributes such as name, birthdate, and timestamps.
 * Includes validation logic requiring both first and last name to be present.
 */
class Person extends BaseModel
{
    /**
     * Returns the database table name for persons.
     *
     * @return string The table name
     */
    public static function tableName(): string
    {
        return 'persons';
    }

    /**
     * Returns the person's first name.
     *
     * @return string|null The first name, or null if not set
     */
    public function getFirstName(): ?string
    {
        return $this->attributes['first_name'] ?? null;
    }

    /**
     * Returns the person's last name.
     *
     * @return string|null The last name, or null if not set
     */
    public function getLastName(): ?string
    {
        return $this->attributes['last_name'] ?? null;
    }

    /**
     * Returns the person's birthdate.
     *
     * @return string|null The birthdate, or null if not set
     */
    public function getBirthdate(): ?string
    {
        return $this->attributes['birthdate'] ?? null;
    }

    /**
     * Returns the timestamp when the person record was created.
     *
     * @return string|null The creation timestamp, or null if not set
     */
    public function getCreatedAt(): ?string
    {
        return $this->attributes['created_at'] ?? null;
    }

    /**
     * Returns the timestamp when the person record was last updated.
     *
     * @return string|null The update timestamp, or null if not set
     */
    public function getUpdatedAt(): ?string
    {
        return $this->attributes['updated_at'] ?? null;
    }

    /**
     * Validates that the person has both first and last name.
     *
     * @return bool True if valid, false otherwise
     */
    public function valid(): bool
    {
        return isset($this->attributes['first_name']) && isset($this->attributes['last_name']);
    }

    /**
     * Returns the list of valid attribute names for the Person model.
     *
     * @return array The attribute names
     */
    protected static function attributeNames(): array
    {
        return ['id', 'first_name', 'last_name', 'birthdate', 'created_at', 'updated_at'];
    }
}
