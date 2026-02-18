<?php

namespace Tent\Tests\Matchers;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\RequestResponseMatchersBuilder;
use Tent\Matchers\StatusCodeMatcher;
use Tent\Matchers\RequestMethodMatcher;
use Tent\Models\Response;
use Tent\Models\Request;

class RequestResponseMatchersBuilderTest extends TestCase
{
    private function mockResponse($code)
    {
        return new Response(['httpCode' => $code]);
    }

    private function mockRequest($method = 'GET')
    {
        return new Request(
            ['requestMethod' => $method, 'requestUrl' => '/']
        );
    }

    public function testBuildWithMatchersAttribute()
    {
        $attributes = [
            'matchers' => [
                [
                    'class' => 'Tent\Matchers\StatusCodeMatcher',
                    'httpCodes' => [200, 201]
                ]
            ]
        ];

        $builder = new RequestResponseMatchersBuilder($attributes);
        $matchers = $builder->build();

        $this->assertIsArray($matchers);
        $this->assertNotEmpty($matchers);
        $this->assertInstanceOf(StatusCodeMatcher::class, $matchers[0]);
    }

    public function testBuildWithDeprecatedHttpCodesAttribute()
    {
        $attributes = [
            'httpCodes' => [200, 201]
        ];

        $builder = new RequestResponseMatchersBuilder($attributes);
        $matchers = $builder->build();

        $this->assertIsArray($matchers);
        $this->assertCount(2, $matchers);
        $this->assertInstanceOf(StatusCodeMatcher::class, $matchers[0]);
        $this->assertInstanceOf(RequestMethodMatcher::class, $matchers[1]);
    }

    public function testBuildWithNoAttributesDefaultsTo200()
    {
        $attributes = [];

        $builder = new RequestResponseMatchersBuilder($attributes);
        $matchers = $builder->build();

        $this->assertIsArray($matchers);
        $this->assertCount(2, $matchers);
        $this->assertInstanceOf(StatusCodeMatcher::class, $matchers[0]);
        $this->assertInstanceOf(RequestMethodMatcher::class, $matchers[1]);
    }

    public function testBuildWithRequestMethodsAttribute()
    {
        $attributes = [
            'requestMethods' => ['POST', 'PUT']
        ];

        $builder = new RequestResponseMatchersBuilder($attributes);
        $matchers = $builder->build();

        $this->assertIsArray($matchers);
        $this->assertCount(2, $matchers);
        $this->assertInstanceOf(StatusCodeMatcher::class, $matchers[0]);
        $this->assertInstanceOf(RequestMethodMatcher::class, $matchers[1]);
    }

    public function testBuildWithBothHttpCodesAndRequestMethods()
    {
        $attributes = [
            'httpCodes' => [200, 201],
            'requestMethods' => ['POST', 'PUT']
        ];

        $builder = new RequestResponseMatchersBuilder($attributes);
        $matchers = $builder->build();

        $this->assertIsArray($matchers);
        $this->assertCount(2, $matchers);
        $this->assertInstanceOf(StatusCodeMatcher::class, $matchers[0]);
        $this->assertInstanceOf(RequestMethodMatcher::class, $matchers[1]);
    }

    public function testBuildPrefersMatchersOverHttpCodes()
    {
        $attributes = [
            'matchers' => [
                [
                    'class' => 'Tent\Matchers\StatusCodeMatcher',
                    'httpCodes' => [200, 201]
                ]
            ],
            'httpCodes' => [500, 501]  // This should be ignored
        ];

        $builder = new RequestResponseMatchersBuilder($attributes);
        $matchers = $builder->build();

        // When matchers is present, httpCodes is ignored and requestMethods is not added
        $this->assertIsArray($matchers);
        $this->assertCount(2, $matchers);
        $this->assertInstanceOf(StatusCodeMatcher::class, $matchers[0]);
        $this->assertInstanceOf(RequestMethodMatcher::class, $matchers[1]);
    }

    public function testMatchResponseWithDeprecatedHttpCodes()
    {
        $attributes = [
            'httpCodes' => [200, 201]
        ];

        $builder = new RequestResponseMatchersBuilder($attributes);
        $matchers = $builder->build();

        // Status code matcher should match 200 and 201
        $this->assertTrue($matchers[0]->matchResponse($this->mockResponse(200)));
        $this->assertTrue($matchers[0]->matchResponse($this->mockResponse(201)));
        $this->assertFalse($matchers[0]->matchResponse($this->mockResponse(500)));
    }

    public function testMatchRequestWithRequest()
    {
        $attributes = [
            'requestMethods' => ['POST', 'PUT']
        ];

        $builder = new RequestResponseMatchersBuilder($attributes);
        $matchers = $builder->build();

        // Request method matcher should match POST and PUT
        $this->assertTrue($matchers[1]->matchRequest($this->mockRequest('POST')));
        $this->assertTrue($matchers[1]->matchRequest($this->mockRequest('PUT')));
        $this->assertFalse($matchers[1]->matchRequest($this->mockRequest('GET')));
    }

    public function testMatchResponseDefaultTo200()
    {
        $attributes = [];

        $builder = new RequestResponseMatchersBuilder($attributes);
        $matchers = $builder->build();

        // Default should match only 200
        $this->assertTrue($matchers[0]->matchResponse($this->mockResponse(200)));
        $this->assertFalse($matchers[0]->matchResponse($this->mockResponse(201)));
    }

    public function testMatchRequestDefaultToGet()
    {
        $attributes = [];

        $builder = new RequestResponseMatchersBuilder($attributes);
        $matchers = $builder->build();

        // Default should match only GET
        $this->assertTrue($matchers[1]->matchRequest($this->mockRequest('GET')));
        $this->assertFalse($matchers[1]->matchRequest($this->mockRequest('POST')));
    }

    public function testAddStatusCodeMatcherWhenMissing()
    {
        $attributes = [
            'matchers' => [
                [
                    'class' => 'Tent\Matchers\RequestMethodMatcher',
                    'methods' => ['POST']
                ]
            ],
            'httpCodes' => [201, 202]
        ];

        $builder = new RequestResponseMatchersBuilder($attributes);
        $matchers = $builder->build();

        // Should have 2 matchers: one from config, one added for StatusCode
        $this->assertCount(2, $matchers);
        // First should be RequestMethodMatcher
        $this->assertInstanceOf(RequestMethodMatcher::class, $matchers[0]);
        // Second should be StatusCodeMatcher with httpCodes from attributes
        $this->assertInstanceOf(StatusCodeMatcher::class, $matchers[1]);
        $this->assertTrue($matchers[1]->matchResponse($this->mockResponse(201)));
        $this->assertTrue($matchers[1]->matchResponse($this->mockResponse(202)));
        $this->assertFalse($matchers[1]->matchResponse($this->mockResponse(200)));
    }
}
