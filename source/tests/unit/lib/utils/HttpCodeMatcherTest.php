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
}
