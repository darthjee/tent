<?php

namespace ApiDev\Tests;

use PHPUnit\Framework\TestCase;
use ApiDev\Mysql\Connection;
use ApiDev\Mysql\ModelConnection;

class ModelConnectionUpdateTest extends TestCase
{
    private $connection;
    private $model;

    protected function setUp(): void
    {
        $this->connection = \ApiDev\Mysql\Configuration::connect();
        $this->model = new ModelConnection($this->connection, 'persons');
    }

    public function testUpdatePerson()
    {
        // First insert a person
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'birthdate' => '1990-01-01'
        ];
        $id = $this->model->insert($data);

        // Update the person
        $updateData = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'birthdate' => '1992-05-15'
        ];
        $this->model->update($id, $updateData);

        // Verify the update
        $row = $this->connection->fetch('SELECT * FROM persons WHERE id = ?', [$id]);
        $this->assertNotEmpty($row);
        $this->assertEquals('Jane', $row['first_name']);
        $this->assertEquals('Smith', $row['last_name']);
        $this->assertEquals('1992-05-15', $row['birthdate']);
    }

    public function testUpdatePartialAttributes()
    {
        // First insert a person
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'birthdate' => '1990-01-01'
        ];
        $id = $this->model->insert($data);

        // Update only first_name
        $updateData = [
            'first_name' => 'Johnny'
        ];
        $this->model->update($id, $updateData);

        // Verify only first_name was updated
        $row = $this->connection->fetch('SELECT * FROM persons WHERE id = ?', [$id]);
        $this->assertNotEmpty($row);
        $this->assertEquals('Johnny', $row['first_name']);
        $this->assertEquals('Doe', $row['last_name']); // Should remain unchanged
        $this->assertEquals('1990-01-01', $row['birthdate']); // Should remain unchanged
    }
}
