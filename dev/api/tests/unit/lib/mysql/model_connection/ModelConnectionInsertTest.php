<?php

namespace ApiDev\Tests;

use PHPUnit\Framework\TestCase;
use ApiDev\Mysql\ModelConnection;

class ModelConnectionInsertTest extends TestCase
{
    private $connection;
    private $model;

    protected function setUp(): void
    {
        $this->connection = \ApiDev\Mysql\Configuration::connect();
        $this->model = new ModelConnection($this->connection, 'persons');
    }

    public function testInsertPerson()
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'birthdate' => '1990-01-01'
        ];
        $id = $this->model->insert($data);
        $this->assertIsNumeric($id);

        $row = $this->connection->fetch('SELECT * FROM persons WHERE id = ?', [$id]);
        $this->assertNotEmpty($row);
        $this->assertEquals('John', $row['first_name']);
        $this->assertEquals('Doe', $row['last_name']);
        $this->assertEquals('1990-01-01', $row['birthdate']);
    }
}
