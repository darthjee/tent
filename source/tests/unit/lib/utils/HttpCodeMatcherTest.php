<?php

namespace Tent\Tests\Utils;

use PHPUnit\Framework\TestCase;
use Tent\Utils\HttpCodeMatcher;

class HttpCodeMatcherTest extends TestCase
{
    public function testMatchReturnsTrueWhenCodeIsInList()
    {
        $this->assertTrue(HttpCodeMatcher::match(200, [200]));
        $this->assertTrue(HttpCodeMatcher::match(201, [201]));
        $this->assertTrue(HttpCodeMatcher::match(200, [200, 201]));
    }

    public function testMatchReturnsFalseWhenCodeIsNotInList()
    {
        $this->assertFalse(HttpCodeMatcher::match(200, [201]));
        $this->assertFalse(HttpCodeMatcher::match(201, [200]));
        $this->assertFalse(HttpCodeMatcher::match(200, []));
        $this->assertFalse(HttpCodeMatcher::match(201, []));
    }

    public function testMatchReturnsTrueWhenCodeIsStringInList()
    {
        $this->assertTrue(HttpCodeMatcher::match(200, ["200"]));
        $this->assertTrue(HttpCodeMatcher::match(201, ["201"]));
        $this->assertTrue(HttpCodeMatcher::match(200, ["200", "201"]));
    }

    public function testMatchReturnsFalseWhenCodeIsNotStringInList()
    {
        $this->assertFalse(HttpCodeMatcher::match(200, ["201"]));
        $this->assertFalse(HttpCodeMatcher::match(201, ["200"]));
        $this->assertFalse(HttpCodeMatcher::match(200, []));
        $this->assertFalse(HttpCodeMatcher::match(201, []));
    }
}
