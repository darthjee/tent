<?php

namespace Tent\Tests\Matchers;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\GetRequestSuccessFilter;
use Tent\Models\Response;
use Tent\Models\Request;
use Tent\Models\ProcessingRequest;

class GetRequestSuccessFilterTest extends TestCase
{
    private function mockRequest($method)
    {
        return new ProcessingRequest([
            'requestMethod' => $method,
            'requestPath' => '/test',
            'queryString' => '',
            'headers' => []
        ]);
    }

    private function mockResponse($code)
    {
        return new Response(['httpCode' => $code]);
    }

    public function testMatchRequestReturnsTrueForGetRequests()
    {
        $filter = new GetRequestSuccessFilter();
        $request = $this->mockRequest('GET');
        $this->assertTrue($filter->matchRequest($request));
    }

    public function testMatchRequestReturnsFalseForPostRequests()
    {
        $filter = new GetRequestSuccessFilter();
        $request = $this->mockRequest('POST');
        $this->assertFalse($filter->matchRequest($request));
    }

    public function testMatchRequestReturnsFalseForPutRequests()
    {
        $filter = new GetRequestSuccessFilter();
        $request = $this->mockRequest('PUT');
        $this->assertFalse($filter->matchRequest($request));
    }

    public function testMatchResponseReturnsTrueFor200()
    {
        $filter = new GetRequestSuccessFilter();
        $response = $this->mockResponse(200);
        $this->assertTrue($filter->matchResponse($response));
    }

    public function testMatchResponseReturnsFalseFor201()
    {
        $filter = new GetRequestSuccessFilter();
        $response = $this->mockResponse(201);
        $this->assertFalse($filter->matchResponse($response));
    }

    public function testMatchResponseReturnsFalseFor404()
    {
        $filter = new GetRequestSuccessFilter();
        $response = $this->mockResponse(404);
        $this->assertFalse($filter->matchResponse($response));
    }

    public function testMatchResponseReturnsFalseFor500()
    {
        $filter = new GetRequestSuccessFilter();
        $response = $this->mockResponse(500);
        $this->assertFalse($filter->matchResponse($response));
    }

    public function testBuild()
    {
        $filter = GetRequestSuccessFilter::build([]);
        $this->assertInstanceOf(GetRequestSuccessFilter::class, $filter);
    }
}
