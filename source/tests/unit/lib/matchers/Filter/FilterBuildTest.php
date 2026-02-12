<?php

namespace Tent\Tests\Matchers;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\Filter;
use Tent\Matchers\StatusCodeMatcher;
use Tent\Models\Response;

class FilterBuildTest extends TestCase
{
    private function mockResponse($code)
    {
        return new Response(['httpCode' => $code]);
    }

    public function testBuildCreatesStatusCodeMatcher()
    {
        $filter = Filter::build([
            'class' => StatusCodeMatcher::class,
            'httpCodes' => [201, 202]
        ]);
        $this->assertInstanceOf(StatusCodeMatcher::class, $filter);
        $this->assertTrue($filter->matchResponse($this->mockResponse(201)));
        $this->assertTrue($filter->matchResponse($this->mockResponse(202)));
        $this->assertFalse($filter->matchResponse($this->mockResponse(200)));
    }

    public function testBuildCreatesStatusCodeMatcherByString()
    {
        $filter = Filter::build([
            'class' => "Tent\Matchers\StatusCodeMatcher",
            'httpCodes' => [201, 202]
        ]);
        $this->assertInstanceOf(StatusCodeMatcher::class, $filter);
        $this->assertTrue($filter->matchResponse($this->mockResponse(201)));
        $this->assertTrue($filter->matchResponse($this->mockResponse(202)));
        $this->assertFalse($filter->matchResponse($this->mockResponse(200)));
    }

    public function testBuildDefaultsTo200()
    {
        $filter = Filter::build([
            'class' => StatusCodeMatcher::class
        ]);
        $this->assertTrue($filter->matchResponse($this->mockResponse(200)));
        $this->assertFalse($filter->matchResponse($this->mockResponse(201)));
    }
}
