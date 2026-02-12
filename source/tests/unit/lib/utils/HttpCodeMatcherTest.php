<?php

namespace Tent\Tests\Utils;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Utils\HttpCodeMatcher;

class HttpCodeMatcherTest extends TestCase
{
    public function testMatchReturnsTrueWhenCodeIsInList()
    {
        $this->assertTrue((new HttpCodeMatcher(200))->match(200));
        $this->assertTrue((new HttpCodeMatcher(201))->match(201));
        $this->assertTrue((new HttpCodeMatcher(200))->match(200));
        $this->assertTrue((new HttpCodeMatcher(201))->match(201));
    }

    public function testMatchReturnsFalseWhenCodeIsNotInList()
    {
        $this->assertFalse((new HttpCodeMatcher(201))->match(200));
        $this->assertFalse((new HttpCodeMatcher(200))->match(201));
    }

    public function testMatchReturnsTrueWhenCodeIsStringInList()
    {
        $this->assertTrue((new HttpCodeMatcher("200"))->match(200));
        $this->assertTrue((new HttpCodeMatcher("201"))->match(201));
        $this->assertTrue((new HttpCodeMatcher("200"))->match(200));
        $this->assertTrue((new HttpCodeMatcher("201"))->match(201));
    }

    public function testMatchReturnsFalseWhenCodeIsNotStringInList()
    {
        $this->assertFalse((new HttpCodeMatcher("201"))->match(200));
        $this->assertFalse((new HttpCodeMatcher("200"))->match(201));
    }

    public function testMatchReturnsTrueForWildcardX()
    {
        $matcher = new HttpCodeMatcher("30x");
        $this->assertTrue($matcher->match(300));
        $this->assertTrue($matcher->match(301));
        $this->assertTrue($matcher->match(309));
        $this->assertFalse($matcher->match(310));
        $this->assertFalse($matcher->match(299));
    }

    public function testMatchReturnsTrueForWildcard4xx()
    {
        $matcher = new HttpCodeMatcher("4xx");
        $this->assertTrue($matcher->match(400));
        $this->assertTrue($matcher->match(401));
        $this->assertTrue($matcher->match(499));
        $this->assertFalse($matcher->match(500));
        $this->assertFalse($matcher->match(399));
    }

    public function testMatchReturnsTrueForWildcard5XXUppercase()
    {
        $matcher = new HttpCodeMatcher("5XX");
        $this->assertTrue($matcher->match(500));
        $this->assertTrue($matcher->match(501));
        $this->assertTrue($matcher->match(599));
        $this->assertFalse($matcher->match(600));
        $this->assertFalse($matcher->match(499));
    }
}
