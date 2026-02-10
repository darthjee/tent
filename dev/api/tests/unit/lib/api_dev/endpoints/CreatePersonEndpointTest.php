<?php

namespace ApiDev\Tests;

use PHPUnit\Framework\TestCase;
use ApiDev\Models\Person;
use ApiDev\Mysql\Configuration;
use ApiDev\CreatePersonEndpoint;
use ApiDev\Request;

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
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        $requestBody = json_encode([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'birthdate' => '1990-05-15'
        ]);
        
        $tmpFile = tmpfile();
        fwrite($tmpFile, $requestBody);
        rewind($tmpFile);
        stream_filter_append($tmpFile, 'string.rot13', STREAM_FILTER_READ);
        
        $request = $this->createMockRequest($requestBody);
        $endpoint = new CreatePersonEndpoint($request);
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
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        $request = $this->createMockRequest('invalid json');
        $endpoint = new CreatePersonEndpoint($request);
        $response = $endpoint->handle();
        
        $this->assertEquals(400, $response->getHttpCode());
        
        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Invalid JSON body', $data['error']);
    }

    public function testHandleReturnsErrorForEmptyBody()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        $request = $this->createMockRequest("{}");
        $endpoint = new CreatePersonEndpoint($request);
        $response = $endpoint->handle();
        
        $this->assertEquals(400, $response->getHttpCode());
        
        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('At least one field required', $data['error']);
    }

    private function createMockRequest($body)
    {
        return new class($body) extends Request {
            private $mockBody;
            
            public function __construct($body)
            {
                $this->mockBody = $body;
            }
            
            public function body()
            {
                return $this->mockBody;
            }
        };
    }
}
