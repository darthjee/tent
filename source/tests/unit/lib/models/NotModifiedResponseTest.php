<?php

namespace Tent\Tests\Models;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Models\NotModifiedResponse;
use Tent\Models\Response;
use Tent\Models\Request;

class NotModifiedResponseTest extends TestCase
{
    private const HEADERS = [
        'ETag: "abc"',
        'Last-Modified: Tue, 14 Nov 2023 22:13:20 GMT'
    ];

    public function testCreatesResponseWith304StatusCode()
    {
        $response = new NotModifiedResponse(new Request([]), self::HEADERS);

        $this->assertEquals(304, $response->httpCode());
    }

    public function testCreatesResponseWithEmptyBody()
    {
        $response = new NotModifiedResponse(new Request([]), self::HEADERS);

        $this->assertSame('', $response->body());
    }

    public function testKeepsGivenHeaders()
    {
        $response = new NotModifiedResponse(new Request([]), self::HEADERS);

        $this->assertEquals(self::HEADERS, $response->headers());
    }

    public function testKeepsRequest()
    {
        $request = new Request([]);
        $response = new NotModifiedResponse($request, self::HEADERS);

        $this->assertSame($request, $response->request());
    }

    public function testExtendsResponse()
    {
        $response = new NotModifiedResponse(new Request([]), self::HEADERS);

        $this->assertInstanceOf(Response::class, $response);
    }
}
