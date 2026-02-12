<?php

namespace Tent\Tests\Matchers;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\StatusCodeMatcher;
use Tent\Models\Response;

class StatusCodeMatcherTest extends TestCase
{
    private function mockResponse($code)
    {
        return new Response(['httpCode' => $code]);
    }

    public function testMatchReturnsTrueWhenCodeIsInList()
    {
        $matcher = new StatusCodeMatcher([200]);
        $this->assertTrue($matcher->match($this->mockResponse(200)));
        $matcher = new StatusCodeMatcher([201]);
        $this->assertTrue($matcher->match($this->mockResponse(201)));
        $matcher = new StatusCodeMatcher([200, 201]);
        $this->assertTrue($matcher->match($this->mockResponse(200)));
    }

    public function testMatchReturnsFalseWhenCodeIsNotInList()
    {
        $matcher = new StatusCodeMatcher([201]);
        $this->assertFalse($matcher->match($this->mockResponse(200)));
        $matcher = new StatusCodeMatcher([200]);
        $this->assertFalse($matcher->match($this->mockResponse(201)));
        $matcher = new StatusCodeMatcher([]);
        $this->assertFalse($matcher->match($this->mockResponse(200)));
        $this->assertFalse($matcher->match($this->mockResponse(201)));
    }

    public function testMatchReturnsTrueWhenCodeIsStringInList()
    {
        $matcher = new StatusCodeMatcher(["200"]);
        $this->assertTrue($matcher->match($this->mockResponse(200)));
        $matcher = new StatusCodeMatcher(["201"]);
        $this->assertTrue($matcher->match($this->mockResponse(201)));
        $matcher = new StatusCodeMatcher(["200", "201"]);
        $this->assertTrue($matcher->match($this->mockResponse(200)));
    }

    public function testMatchReturnsFalseWhenCodeIsNotStringInList()
    {
        $matcher = new StatusCodeMatcher(["201"]);
        $this->assertFalse($matcher->match($this->mockResponse(200)));
        $matcher = new StatusCodeMatcher(["200"]);
        $this->assertFalse($matcher->match($this->mockResponse(201)));
        $matcher = new StatusCodeMatcher([]);
        $this->assertFalse($matcher->match($this->mockResponse(200)));
        $this->assertFalse($matcher->match($this->mockResponse(201)));
    }

    public function testMatchReturnsTrueForWildcardX()
    {
        $matcher = new StatusCodeMatcher(["30x"]);
        $this->assertTrue($matcher->match($this->mockResponse(300)));
        $this->assertTrue($matcher->match($this->mockResponse(301)));
        $this->assertTrue($matcher->match($this->mockResponse(309)));
        $this->assertFalse($matcher->match($this->mockResponse(310)));
        $this->assertFalse($matcher->match($this->mockResponse(299)));
    }

    public function testMatchReturnsTrueForWildcard4xx()
    {
        $matcher = new StatusCodeMatcher(["4xx"]);
        $this->assertTrue($matcher->match($this->mockResponse(400)));
        $this->assertTrue($matcher->match($this->mockResponse(401)));
        $this->assertTrue($matcher->match($this->mockResponse(499)));
        $this->assertFalse($matcher->match($this->mockResponse(500)));
        $this->assertFalse($matcher->match($this->mockResponse(399)));
    }

    public function testMatchReturnsTrueForWildcard5XXUppercase()
    {
        $matcher = new StatusCodeMatcher(["5XX"]);
        $this->assertTrue($matcher->match($this->mockResponse(500)));
        $this->assertTrue($matcher->match($this->mockResponse(501)));
        $this->assertTrue($matcher->match($this->mockResponse(599)));
        $this->assertFalse($matcher->match($this->mockResponse(600)));
        $this->assertFalse($matcher->match($this->mockResponse(499)));
    }

    //////// Build tests
    public function testBuildCreatesMatcherWithGivenCodes()
    {
        $matcher = StatusCodeMatcher::build(['httpCodes' => [201, 202]]);
        $this->assertTrue($matcher->match($this->mockResponse(201)));
        $this->assertTrue($matcher->match($this->mockResponse(202)));
        $this->assertFalse($matcher->match($this->mockResponse(200)));
    }

    public function testBuildDefaultsTo200()
    {
        $matcher = StatusCodeMatcher::build([]);
        $this->assertTrue($matcher->match($this->mockResponse(200)));
        $this->assertFalse($matcher->match($this->mockResponse(201)));
    }
}
