<?php

namespace ApiDev\Tests;

use PHPUnit\Framework\TestCase;
use ApiDev\Mysql\Configuration;
use ApiDev\UpdatePersonEndpoint;
use ApiDev\MockRequest;

require_once __DIR__ . '/../../../../support/tests_loader.php';

class UpdatePersonEndpointTest extends TestCase
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

    public function testHandleReturns200WithUpdatedPersonJson(): void
    {
        $body = json_encode(['first_name' => 'John', 'birthdate' => '1985-03-20']);
        $request = new MockRequest([
            'requestMethod' => 'PATCH',
            'requestUrl' => '/persons/' . $this->personId,
            'body' => $body,
        ]);
        $endpoint = new UpdatePersonEndpoint($request);
        $response = $endpoint->handle();

        $this->assertEquals(200, $response->getHttpCode());
        $this->assertEquals(['Content-Type: application/json'], $response->getHeaders());

        $data = json_decode($response->getBody(), true);
        $this->assertIsArray($data);
        $this->assertEquals($this->personId, $data['id']);
        $this->assertEquals('John', $data['first_name']);
        $this->assertEquals('Doe', $data['last_name']);
        $this->assertEquals('1985-03-20', $data['birthdate']);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('updated_at', $data);
    }

    public function testHandleReturns404WhenPersonNotFound(): void
    {
        $body = json_encode(['first_name' => 'John']);
        $request = new MockRequest([
            'requestMethod' => 'PATCH',
            'requestUrl' => '/persons/999999',
            'body' => $body,
        ]);
        $endpoint = new UpdatePersonEndpoint($request);
        $response = $endpoint->handle();

        $this->assertEquals(404, $response->getHttpCode());
        $this->assertEquals(['Content-Type: application/json'], $response->getHeaders());

        $data = json_decode($response->getBody(), true);
        $this->assertEquals(['error' => 'Person not found'], $data);
    }

    public function testHandleReturns422WithInvalidBody(): void
    {
        $request = new MockRequest([
            'requestMethod' => 'PATCH',
            'requestUrl' => '/persons/' . $this->personId,
            'body' => 'not-valid-json',
        ]);
        $endpoint = new UpdatePersonEndpoint($request);
        $response = $endpoint->handle();

        $this->assertEquals(422, $response->getHttpCode());
        $this->assertEquals(['Content-Type: application/json'], $response->getHeaders());

        $data = json_decode($response->getBody(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('error', $data);
    }

    public function testHandleReturns422WhenNoAcceptedFieldsProvided(): void
    {
        $body = json_encode(['unknown_field' => 'value']);
        $request = new MockRequest([
            'requestMethod' => 'PATCH',
            'requestUrl' => '/persons/' . $this->personId,
            'body' => $body,
        ]);
        $endpoint = new UpdatePersonEndpoint($request);
        $response = $endpoint->handle();

        $this->assertEquals(422, $response->getHttpCode());
        $this->assertEquals(['Content-Type: application/json'], $response->getHeaders());

        $data = json_decode($response->getBody(), true);
        $this->assertEquals(['error' => 'At least one field required'], $data);
    }
}
