<?php

namespace Tent\Tests\Matchers;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\Filter;
use Tent\Matchers\StatusCodeMatcher;
use Tent\Models\Response;

class FilterBuildFiltersTest extends TestCase
{
    private function mockResponse($code)
    {
        return new Response(['httpCode' => $code]);
    }

    public function testBuildFiltersCreatesMultipleFilters()
    {
        $filters = Filter::buildFilters([
            [
                'class' => StatusCodeMatcher::class,
                'httpCodes' => [200]
            ],
            [
                'class' => StatusCodeMatcher::class,
                'httpCodes' => [201]
            ]
        ]);
        $this->assertCount(2, $filters);
        $this->assertInstanceOf(StatusCodeMatcher::class, $filters[0]);
        $this->assertInstanceOf(StatusCodeMatcher::class, $filters[1]);
        $this->assertTrue($filters[0]->matchResponse($this->mockResponse(200)));
        $this->assertFalse($filters[0]->matchResponse($this->mockResponse(201)));
        $this->assertTrue($filters[1]->matchResponse($this->mockResponse(201)));
        $this->assertFalse($filters[1]->matchResponse($this->mockResponse(200)));
    }

    public function testBuildFiltersEmptyArray()
    {
        $filters = Filter::buildFilters([]);
        $this->assertIsArray($filters);
        $this->assertCount(0, $filters);
    }
}
