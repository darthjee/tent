<?php

namespace ApiDev\Tests;

use PHPUnit\Framework\TestCase;
use ApiDev\Models\Person;
use ApiDev\Mysql\Configuration;

require_once __DIR__ . '/../../../../../support/tests_loader.php';

class PersonSaveTest extends TestCase
{
    private $connection;

    protected function setUp(): void
    {
        $this->connection = Configuration::connect();
        $this->connection->execute('DELETE FROM persons');
    }

    public function testSaveNewPerson()
    {
        // Create a new person without ID
        $person = new Person([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'birthdate' => '1995-06-15'
        ]);

        // ID should be null before save
        $this->assertNull($person->getId());

        // Save the person
        $person->save();

        // ID should be set after save
        $this->assertNotNull($person->getId());
        $this->assertIsNumeric($person->getId());

        // Verify the person was inserted in database
        $row = $this->connection->fetch('SELECT * FROM persons WHERE id = ?', [$person->getId()]);
        $this->assertNotEmpty($row);
        $this->assertEquals('Jane', $row['first_name']);
        $this->assertEquals('Doe', $row['last_name']);
        $this->assertEquals('1995-06-15', $row['birthdate']);
    }

    public function testSaveExistingPerson()
    {
        // First insert a person
        $query = 'INSERT INTO persons (first_name, last_name, birthdate) VALUES (?, ?, ?)';
        $this->connection->execute($query, ['John', 'Smith', '1990-01-01']);
        $id = $this->connection->lastInsertId();

        // Load the person and modify it
        $person = new Person([
            'id' => $id,
            'first_name' => 'Johnny',
            'last_name' => 'Smithson',
            'birthdate' => '1990-01-01'
        ]);

        // Save should update the existing record
        $person->save();

        // Verify the person was updated in database
        $row = $this->connection->fetch('SELECT * FROM persons WHERE id = ?', [$id]);
        $this->assertNotEmpty($row);
        $this->assertEquals('Johnny', $row['first_name']);
        $this->assertEquals('Smithson', $row['last_name']);
        $this->assertEquals('1990-01-01', $row['birthdate']);
    }

    public function testSavePreservesId()
    {
        // Create and save a new person
        $person = new Person([
            'first_name' => 'Alice',
            'last_name' => 'Wonder',
            'birthdate' => '1988-03-20'
        ]);
        $person->save();
        $firstId = $person->getId();

        // Save again (should update, not insert)
        $person->save();
        $secondId = $person->getId();

        // ID should remain the same
        $this->assertEquals($firstId, $secondId);

        // Should only have one record in database
        $count = $this->connection->fetch('SELECT COUNT(*) as total FROM persons WHERE id = ?', [$firstId]);
        $this->assertEquals(1, $count['total']);
    }
}
