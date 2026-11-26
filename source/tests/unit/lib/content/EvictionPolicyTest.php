<?php

namespace Tent\Tests\Content;

use PHPUnit\Framework\TestCase;
use Tent\Content\EvictionPolicy;

class EvictionPolicyTest extends TestCase
{
    public function testEmptyNeverEvicts()
    {
        $policy = new EvictionPolicy([]);

        $this->assertTrue($policy->isEmpty());
        $this->assertFalse($policy->evicts(404));
    }

    public function testExactCode()
    {
        $policy = new EvictionPolicy([404]);

        $this->assertFalse($policy->isEmpty());
        $this->assertTrue($policy->evicts(404));
        $this->assertFalse($policy->evicts(500));
    }

    public function testWildcardFamily()
    {
        $policy = new EvictionPolicy(['4xx']);

        $this->assertTrue($policy->evicts(400));
        $this->assertTrue($policy->evicts(451));
        $this->assertFalse($policy->evicts(500));
    }

    public function testBuildUsesDefaultWhenNull()
    {
        $policy = EvictionPolicy::build(null, ['4xx']);

        $this->assertTrue($policy->evicts(404));
    }

    public function testBuildExplicitEmptyOverridesDefault()
    {
        $policy = EvictionPolicy::build([], ['4xx']);

        $this->assertTrue($policy->isEmpty());
        $this->assertFalse($policy->evicts(404));
    }

    public function testInvalidEntryThrows()
    {
        $this->expectException(\InvalidArgumentException::class);

        new EvictionPolicy(['abc']);
    }

    public function testNonScalarEntryThrows()
    {
        $this->expectException(\InvalidArgumentException::class);

        new EvictionPolicy([[404]]);
    }
}
