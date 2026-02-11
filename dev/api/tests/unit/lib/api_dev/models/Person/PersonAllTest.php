<?php

namespace ApiDev\Tests;

use PHPUnit\Framework\TestCase;
use ApiDev\Models\Person;
use ApiDev\Mysql\Configuration;

require_once __DIR__ . '/../../../../../support/tests_loader.php';

class PersonAllTest extends TestCase
{
    protected function setUp(): void
    {
        $connection = Configuration::connect();
        $connection->execute('DELETE FROM persons');

        $query = 'INSERT INTO persons (first_name, last_name, birthdate) VALUES (?, ?, ?)';
        $connection->execute($query, ['Alice', 'Smith', '1991-01-01']);
        $connection->execute($query, ['Bob', 'Jones', '1992-02-02']);
        $connection->execute($query, ['Carol', 'Brown', '1993-03-03']);
    }

    public function testAllReturnsAllPersons()
    {
        $persons = Person::all();
        $this->assertIsArray($persons);
        $this->assertCount(3, $persons);
        $this->assertInstanceOf(Person::class, $persons[0]);
        $names = array_map(function ($person) {
            return $person->getFirstName();
        }, $persons);
        $this->assertContains('Alice', $names);
        $this->assertContains('Bob', $names);
        $this->assertContains('Carol', $names);
    }
}
