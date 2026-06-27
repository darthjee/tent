<?php

namespace ApiDev\Tests;

use PHPUnit\Framework\TestCase;
use ApiDev\Mysql\Configuration;
use ApiDev\ShowPersonEndpoint;
use ApiDev\MockRequest;

require_once __DIR__ . '/../../../../support/tests_loader.php';

class ShowPersonEndpointTest extends TestCase
{
    /**
     * @var int The ID of the person inserted in setUp
     */
    private int $personId;

    protected function setUp(): void
    {
        $connection = Configuration::connect();
        $connection->execute('DELETE FROM persons');
        $query = 'INSERT INTO persons (first_name, last_name, birthdate) VALUES (?, ?, ?)';
        $connection->execute($query, ['Jane', 'Doe', '1990-05-15']);
        $this->personId = (int) $connection->lastInsertId();
    }

    public function testHandleReturns200WithPersonJson(): void
    {
        $request = new MockRequest(['requestUrl' => '/persons/' . $this->personId]);
        $endpoint = new ShowPersonEndpoint($request);
        $response = $endpoint->handle();

        $this->assertEquals(200, $response->getHttpCode());
        $this->assertEquals(['Content-Type: application/json'], $response->getHeaders());

        $data = json_decode($response->getBody(), true);
        $this->assertIsArray($data);
        $this->assertEquals($this->personId, $data['id']);
        $this->assertEquals('Jane', $data['first_name']);
        $this->assertEquals('Doe', $data['last_name']);
        $this->assertEquals('1990-05-15', $data['birthdate']);
    }

    public function testHandleReturns404WhenPersonNotFound(): void
    {
        $request = new MockRequest(['requestUrl' => '/persons/999999']);
        $endpoint = new ShowPersonEndpoint($request);
        $response = $endpoint->handle();

        $this->assertEquals(404, $response->getHttpCode());
        $this->assertEquals(['Content-Type: application/json'], $response->getHeaders());

        $data = json_decode($response->getBody(), true);
        $this->assertEquals(['error' => 'Person not found'], $data);
    }
}
