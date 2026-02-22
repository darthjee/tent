<?php

namespace Tent\Tests\Matchers\RequestMatcher;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\RequestMatcher;
use Tent\Matchers\ExactRequestMatcher;
use Tent\Matchers\BeginsWithRequestMatcher;
use Tent\Models\Request;

class RequestMatcherBuildTest extends TestCase
{
    public function testBuildCreatesRequestMatcherWithAllFields()
    {
        $matcher = RequestMatcher::build([
            'method' => 'GET',
            'uri' => '/users',
            'type' => 'exact'
        ]);
        $this->assertInstanceOf(RequestMatcher::class, $matcher);

        $request = $this->createMock(Request::class);
        $request->method('requestMethod')->willReturn('GET');
        $request->method('requestPath')->willReturn('/users');
        $this->assertTrue($matcher->matches($request));
    }

    public function testBuildDefaultsTypeToExact()
    {
        $matcher = RequestMatcher::build([
            'method' => 'POST',
            'uri' => '/api',
        ]);
        $this->assertInstanceOf(RequestMatcher::class, $matcher);

        $request = $this->createMock(Request::class);
        $request->method('requestMethod')->willReturn('POST');
        $request->method('requestPath')->willReturn('/api');
        $this->assertTrue($matcher->matches($request));
    }

    public function testBuildMatchersCreatesMultipleMatchers()
    {
        $attributes = [
            ['method' => 'GET', 'uri' => '/users', 'type' => 'exact'],
            ['method' => 'POST', 'uri' => '/users', 'type' => 'begins_with'],
            ['method' => null, 'uri' => '/admin', 'type' => 'exact'],
        ];

        $matchers = RequestMatcher::buildMatchers($attributes);

        $this->assertCount(3, $matchers);
        $this->assertInstanceOf(ExactRequestMatcher::class, $matchers[0]);
        $this->assertInstanceOf(BeginsWithRequestMatcher::class, $matchers[1]);
        $this->assertInstanceOf(ExactRequestMatcher::class, $matchers[2]);

        $this->assertEquals('GET', $this->getPrivateProperty($matchers[0], 'requestMethod'));
        $this->assertEquals('/users', $this->getPrivateProperty($matchers[0], 'requestUri'));

        $this->assertEquals('POST', $this->getPrivateProperty($matchers[1], 'requestMethod'));
        $this->assertEquals('/users', $this->getPrivateProperty($matchers[1], 'requestUri'));

        $this->assertNull($this->getPrivateProperty($matchers[2], 'requestMethod'));
        $this->assertEquals('/admin', $this->getPrivateProperty($matchers[2], 'requestUri'));
    }

    private function getPrivateProperty($object, $property)
    {
        $reflection = new \ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }
}
