<?php

namespace ApiDev\Tests;

require_once __DIR__ . '/../../../../support/tests_loader.php';

use PHPUnit\Framework\TestCase;
use ApiDev\Models\Person;
use ApiDev\Mysql\Configuration;

class PersonAllTest extends TestCase
{
    protected function setUp(): void
    {
        $connection = Configuration::connect();
        $connection->execute('DELETE FROM persons');
        $connection->execute('INSERT INTO persons (first_name, last_name, birthdate) VALUES (?, ?, ?)', ['Alice', 'Smith', '1991-01-01']);
        $connection->execute('INSERT INTO persons (first_name, last_name, birthdate) VALUES (?, ?, ?)', ['Bob', 'Jones', '1992-02-02']);
        $connection->execute('INSERT INTO persons (first_name, last_name, birthdate) VALUES (?, ?, ?)', ['Carol', 'Brown', '1993-03-03']);
    }

    public function testAllReturnsAllPersons()
    {
        $persons = Person::all();
        $this->assertIsArray($persons);
        $this->assertCount(3, $persons);
        $this->assertInstanceOf(Person::class, $persons[0]);
        $names = array_map(function ($p) {
            return $p->getFirstName();
        }, $persons);
        $this->assertContains('Alice', $names);
        $this->assertContains('Bob', $names);
        $this->assertContains('Carol', $names);
    }
}
