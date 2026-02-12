<?php

namespace Tent\Tests\Models;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Models\MissingResponse;
use Tent\Models\Response;
use Tent\Models\Request;

class MissingResponseTest extends TestCase
{
    public function testCreatesResponseWith404StatusCode()
    {
        $request = new Request([]);
        $response = new MissingResponse($request);

        $this->assertEquals(404, $response->httpCode());
    }

    public function testCreatesResponseWithNotFoundBody()
    {
        $request = new Request([]);
        $response = new MissingResponse($request);

        $this->assertEquals("Not Found", $response->body());
    }

    public function testCreatesResponseWithTextPlainContentType()
    {
        $request = new Request([]);
        $response = new MissingResponse($request);

        $this->assertEquals(['Content-Type: text/plain'], $response->headers());
    }

    public function testExtendsResponse()
    {
        $request = new Request([]);
        $response = new MissingResponse($request);

        $this->assertInstanceOf(Response::class, $response);
    }
}
