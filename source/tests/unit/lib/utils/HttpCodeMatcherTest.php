<?php

namespace Tent\Tests\Utils;

use PHPUnit\Framework\TestCase;
use Tent\Utils\HttpCodeMatcher;

class HttpCodeMatcherTest extends TestCase
{
    public function testMatchReturnsTrueWhenCodeIsInList()
    {
        $this->assertTrue(HttpCodeMatcher::matchAny(200, [200]));
        $this->assertTrue(HttpCodeMatcher::matchAny(201, [201]));
        $this->assertTrue(HttpCodeMatcher::matchAny(200, [200, 201]));
    }

    public function testMatchReturnsFalseWhenCodeIsNotInList()
    {
        $this->assertFalse(HttpCodeMatcher::matchAny(200, [201]));
        $this->assertFalse(HttpCodeMatcher::matchAny(201, [200]));
        $this->assertFalse(HttpCodeMatcher::matchAny(200, []));
        $this->assertFalse(HttpCodeMatcher::matchAny(201, []));
    }

    public function testMatchReturnsTrueWhenCodeIsStringInList()
    {
        $this->assertTrue(HttpCodeMatcher::matchAny(200, ["200"]));
        $this->assertTrue(HttpCodeMatcher::matchAny(201, ["201"]));
        $this->assertTrue(HttpCodeMatcher::matchAny(200, ["200", "201"]));
    }

    public function testMatchReturnsFalseWhenCodeIsNotStringInList()
    {
        $this->assertFalse(HttpCodeMatcher::matchAny(200, ["201"]));
        $this->assertFalse(HttpCodeMatcher::matchAny(201, ["200"]));
        $this->assertFalse(HttpCodeMatcher::matchAny(200, []));
        $this->assertFalse(HttpCodeMatcher::matchAny(201, []));
    }

    public function testMatchReturnsTrueForWildcardX()
    {
        $this->assertTrue(HttpCodeMatcher::matchAny(300, ["30x"]));
        $this->assertTrue(HttpCodeMatcher::matchAny(301, ["30x"]));
        $this->assertTrue(HttpCodeMatcher::matchAny(309, ["30x"]));

        $this->assertFalse(HttpCodeMatcher::matchAny(310, ["30x"]));
        $this->assertFalse(HttpCodeMatcher::matchAny(299, ["30x"]));
    }

    public function testMatchReturnsTrueForWildcard4xx()
    {
        $this->assertTrue(HttpCodeMatcher::matchAny(400, ["4xx"]));
        $this->assertTrue(HttpCodeMatcher::matchAny(401, ["4xx"]));
        $this->assertTrue(HttpCodeMatcher::matchAny(499, ["4xx"]));

        $this->assertFalse(HttpCodeMatcher::matchAny(500, ["4xx"]));
        $this->assertFalse(HttpCodeMatcher::matchAny(399, ["4xx"]));
    }
}
