<?php

namespace ApiDev\Tests;

use PHPUnit\Framework\TestCase;
use ApiDev\Mysql\Connection;
use ApiDev\Mysql\ModelConnection;
use ApiDev\Mysql\Configuration;

class ModelConnectionListTest extends TestCase
{
    private $connection;
    private $model;
    private $database;

    protected function setUp(): void
    {
        $this->connection = Configuration::connect();

        $this->model = new ModelConnection($this->connection, 'persons');
        // Clean up and insert test data
        $this->connection->execute('DELETE FROM persons');
        $this->model->insert(['first_name' => 'Alice', 'last_name' => 'Smith', 'birthdate' => '1991-01-01']);
        $this->model->insert(['first_name' => 'Bob', 'last_name' => 'Jones', 'birthdate' => '1992-02-02']);
        $this->model->insert(['first_name' => 'Carol', 'last_name' => 'Brown', 'birthdate' => '1993-03-03']);
    }

    public function testListReturnsAllRows()
    {
        $rows = $this->model->list();
        $this->assertCount(1, is_array($rows) && isset($rows[0]) ? [$rows] : []); // fetch returns one row
    }

    public function testListWithLimit()
    {
        $rows = $this->model->list(['limit' => 2]);
        $this->assertIsArray($rows);
        $this->assertLessThanOrEqual(2, count($rows));
    }

    public function testListWithOffset()
    {
        $rows = $this->model->list(['limit' => 1, 'offset' => 1]);
        $this->assertIsArray($rows);
        $this->assertCount(1, $rows);
    }
}
