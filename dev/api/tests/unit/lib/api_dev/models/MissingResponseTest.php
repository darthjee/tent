<?php

namespace ApiDev\Tests;

use PHPUnit\Framework\TestCase;
use ApiDev\MissingResponse;

require_once __DIR__ . '/../../../../support/tests_loader.php';

class MissingResponseTest extends TestCase
{
    public function testCreatesResponseWith404StatusCode()
    {
        $response = new MissingResponse();

        $this->assertEquals(404, $response->httpCode);
    }

    public function testCreatesResponseWithNotFoundBody()
    {
        $response = new MissingResponse();

        $this->assertEquals("Not Found", $response->body);
    }

    public function testCreatesResponseWithTextPlainContentType()
    {
        $response = new MissingResponse();

        $this->assertEquals(['Content-Type: text/plain'], $response->headerLines);
    }

    public function testExtendsResponse()
    {
        $response = new MissingResponse();

        $this->assertInstanceOf(\ApiDev\Response::class, $response);
    }
}
