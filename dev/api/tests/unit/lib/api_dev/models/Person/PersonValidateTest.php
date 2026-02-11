<?php

namespace ApiDev\Tests;

use PHPUnit\Framework\TestCase;
use ApiDev\Models\Person;
use ApiDev\Exceptions\InvalidModelException;

require_once __DIR__ . '/../../../../../support/tests_loader.php';

class PersonValidateTest extends TestCase
{
    public function testValidateDoesNotThrowExceptionWhenPersonIsValid()
    {
        $person = new Person([
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);
        
        // Should not throw any exception
        $person->validate();
        
        // If we get here, the test passed
        $this->assertTrue(true);
    }

    public function testValidateThrowsExceptionWhenFirstNameIsMissing()
    {
        $person = new Person([
            'last_name' => 'Doe'
        ]);
        
        $this->expectException(InvalidModelException::class);
        $this->expectExceptionMessage('Invalid model attributes');
        
        $person->validate();
    }

    public function testValidateThrowsExceptionWhenLastNameIsMissing()
    {
        $person = new Person([
            'first_name' => 'John'
        ]);
        
        $this->expectException(InvalidModelException::class);
        $this->expectExceptionMessage('Invalid model attributes');
        
        $person->validate();
    }

    public function testValidateThrowsExceptionWhenBothNamesAreMissing()
    {
        $person = new Person([
            'birthdate' => '1990-01-01'
        ]);
        
        $this->expectException(InvalidModelException::class);
        $this->expectExceptionMessage('Invalid model attributes');
        
        $person->validate();
    }

    public function testValidateThrowsExceptionWhenAttributesAreEmpty()
    {
        $person = new Person([]);
        
        $this->expectException(InvalidModelException::class);
        $this->expectExceptionMessage('Invalid model attributes');
        
        $person->validate();
    }
}
