<?php

namespace ApiDev\Models;

use ApiDev\Mysql\ModelConnection;
use ApiDev\Mysql\Configuration;
use ApiDev\Models\BaseModel;

class Person extends BaseModel
{
    public static function tableName(): string
    {
        return 'persons';
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

    public function valid(): bool
    {
        return isset($this->attributes['first_name']) && isset($this->attributes['last_name']);
    }
}
