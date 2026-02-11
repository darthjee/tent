<?php

namespace ApiDev\Tests;

use PHPUnit\Framework\TestCase;
use ApiDev\Models\Person;
use ApiDev\Mysql\Configuration;
use ApiDev\CreatePersonEndpoint;
use ApiDev\MockRequest;

require_once __DIR__ . '/../../../../support/tests_loader.php';

class CreatePersonEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        $connection = Configuration::connect();
        $connection->execute('DELETE FROM persons');
    }

    public function testHandleCreatesPersonWithAllFields()
    {
        $requestBody = json_encode([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'birthdate' => '1990-05-15'
        ]);

        $endpoint = $this->initEndpoint($requestBody);
        $response = $endpoint->handle();

        $this->assertEquals(201, $response->getHttpCode());
        $this->assertEquals(['Content-Type: application/json'], $response->getHeaders());

        $data = json_decode($response->getBody(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('John', $data['first_name']);
        $this->assertEquals('Doe', $data['last_name']);
        $this->assertEquals('1990-05-15', $data['birthdate']);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('updated_at', $data);

        $persons = Person::all();
        $this->assertCount(1, $persons);
    }

    public function testHandleReturnsErrorForInvalidJson()
    {
        $endpoint = $this->initEndpoint('invalid json');
        $response = $endpoint->handle();

        $this->assertEquals(400, $response->getHttpCode());

        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Invalid JSON body', $data['error']);
    }

    public function testHandleReturnsErrorForEmptyBody()
    {
        $endpoint = $this->initEndpoint('{}');
        $response = $endpoint->handle();

        $this->assertEquals(400, $response->getHttpCode());

        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('At least one field required', $data['error']);
    }

    private function initEndpoint(string $body): CreatePersonEndpoint
    {
        $request = new MockRequest(['body' => $body, 'requestMethod' => 'POST']);
        return new CreatePersonEndpoint($request);
    }
}
