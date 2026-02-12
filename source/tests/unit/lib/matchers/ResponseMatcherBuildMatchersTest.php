<?php

namespace Tent\Tests\Matchers;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\ResponseMatcher;
use Tent\Matchers\StatusCodeMatcher;
use Tent\Models\Response;

class ResponseMatcherBuildMatchersTest extends TestCase
{
    private function mockResponse($code)
    {
        return new Response(['httpCode' => $code]);
    }

    public function testBuildMatchersCreatesMultipleMatchers()
    {
        $matchers = ResponseMatcher::buildMatchers([
            [
                'class' => StatusCodeMatcher::class,
                'httpCodes' => [200]
            ],
            [
                'class' => StatusCodeMatcher::class,
                'httpCodes' => [201]
            ]
        ]);
        $this->assertCount(2, $matchers);
        $this->assertInstanceOf(StatusCodeMatcher::class, $matchers[0]);
        $this->assertInstanceOf(StatusCodeMatcher::class, $matchers[1]);
        $this->assertTrue($matchers[0]->match($this->mockResponse(200)));
        $this->assertFalse($matchers[0]->match($this->mockResponse(201)));
        $this->assertTrue($matchers[1]->match($this->mockResponse(201)));
        $this->assertFalse($matchers[1]->match($this->mockResponse(200)));
    }

    public function testBuildMatchersEmptyArray()
    {
        $matchers = ResponseMatcher::buildMatchers([]);
        $this->assertIsArray($matchers);
        $this->assertCount(0, $matchers);
    }
}
