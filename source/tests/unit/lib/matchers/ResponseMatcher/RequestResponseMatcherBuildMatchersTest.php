<?php

namespace Tent\Tests\Matchers;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\RequestResponseMatcher;
use Tent\Matchers\StatusCodeMatcher;
use Tent\Models\Response;

class RequestResponseMatcherBuildMatchersTest extends TestCase
{
    private function mockResponse($code)
    {
        return new Response(['httpCode' => $code]);
    }

    public function testBuildMatchersCreatesMultipleMatchers()
    {
        $matchers = RequestResponseMatcher::buildMatchers([
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
        $this->assertTrue($matchers[0]->matchResponse($this->mockResponse(200)));
        $this->assertFalse($matchers[0]->matchResponse($this->mockResponse(201)));
        $this->assertTrue($matchers[1]->matchResponse($this->mockResponse(201)));
        $this->assertFalse($matchers[1]->matchResponse($this->mockResponse(200)));
    }

    public function testBuildMatchersEmptyArray()
    {
        $matchers = RequestResponseMatcher::buildMatchers([]);
        $this->assertIsArray($matchers);
        $this->assertCount(0, $matchers);
    }
}
