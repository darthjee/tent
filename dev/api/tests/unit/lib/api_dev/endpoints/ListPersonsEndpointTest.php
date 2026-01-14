<?php

namespace ApiDev\Tests;

use PHPUnit\Framework\TestCase;
use ApiDev\Models\Person;
use ApiDev\Mysql\Configuration;
use ApiDev\ListPersonsEndpoint;
use ApiDev\Request;

class ListPersonsEndpointTest extends TestCase
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

    public function testHandleReturnsAllPersonsJson()
    {
        $request = new Request();
        $endpoint = new ListPersonsEndpoint($request);
        $response = $endpoint->handle();
        $this->assertEquals(200, $response->getHttpCode());
        $this->assertEquals(['Content-Type: application/json'], $response->getHeaders());
        $data = json_decode($response->getBody(), true);
        $this->assertIsArray($data);
        $this->assertCount(3, $data);
        $names = array_column($data, 'first_name');
        $this->assertContains('Alice', $names);
        $this->assertContains('Bob', $names);
        $this->assertContains('Carol', $names);
    }
}
