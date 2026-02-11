<?php

namespace ApiDev\Tests;

use PHPUnit\Framework\TestCase;
use ApiDev\Models\Person;

require_once __DIR__ . '/../../../../../support/tests_loader.php';

class PersonValidTest extends TestCase
{
    public function testValidReturnsTrueWhenFirstNameAndLastNameArePresent()
    {
        $person = new Person([
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);
        
        $this->assertTrue($person->valid());
    }

    public function testValidReturnsFalseWhenFirstNameIsMissing()
    {
        $person = new Person([
            'last_name' => 'Doe'
        ]);
        
        $this->assertFalse($person->valid());
    }

    public function testValidReturnsFalseWhenLastNameIsMissing()
    {
        $person = new Person([
            'first_name' => 'John'
        ]);
        
        $this->assertFalse($person->valid());
    }

    public function testValidReturnsFalseWhenBothNamesAreMissing()
    {
        $person = new Person([
            'birthdate' => '1990-01-01'
        ]);
        
        $this->assertFalse($person->valid());
    }

    public function testValidReturnsFalseWhenAttributesAreEmpty()
    {
        $person = new Person([]);
        
        $this->assertFalse($person->valid());
    }

    public function testValidIgnoresOtherAttributes()
    {
        $person = new Person([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'birthdate' => '1990-01-01',
            'created_at' => '2024-01-01 00:00:00'
        ]);
        
        $this->assertTrue($person->valid());
    }
}
