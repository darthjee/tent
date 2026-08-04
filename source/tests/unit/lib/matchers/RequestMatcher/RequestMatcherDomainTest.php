<?php

namespace Tent\Tests\Matchers\RequestMatcher;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\ExactRequestMatcher;
use Tent\Models\Request;

class RequestMatcherDomainTest extends TestCase
{
    public function testMatchesWithExactDomain()
    {
        $request = $this->createMockRequest('GET', '/home', 'mydomain.com');
        $matcher = new ExactRequestMatcher('GET', '/home', 'mydomain.com');

        $this->assertTrue($matcher->matches($request));
    }

    public function testDoesNotMatchWithDifferentDomain()
    {
        $request = $this->createMockRequest('GET', '/home', 'otherdomain.com');
        $matcher = new ExactRequestMatcher('GET', '/home', 'mydomain.com');

        $this->assertFalse($matcher->matches($request));
    }

    public function testMatchesWithLeadingWildcard()
    {
        $request = $this->createMockRequest('GET', '/home', 'api.mydomain.com');
        $matcher = new ExactRequestMatcher('GET', '/home', '%.mydomain.com');

        $this->assertTrue($matcher->matches($request));
    }

    public function testMatchesWithTrailingWildcard()
    {
        $request = $this->createMockRequest('GET', '/home', 'mydomain.com.br');
        $matcher = new ExactRequestMatcher('GET', '/home', 'mydomain.%');

        $this->assertTrue($matcher->matches($request));
    }

    public function testMatchesWithMidPatternWildcard()
    {
        $request = $this->createMockRequest('GET', '/home', 'my-super-domain.com');
        $matcher = new ExactRequestMatcher('GET', '/home', 'my%domain.com');

        $this->assertTrue($matcher->matches($request));
    }

    public function testMatchesWithMultipleWildcards()
    {
        $request = $this->createMockRequest('GET', '/home', 'api.my-super-domain.com.br');
        $matcher = new ExactRequestMatcher('GET', '/home', '%.my%domain.%');

        $this->assertTrue($matcher->matches($request));
    }

    public function testDoesNotMatchWildcardWhenPatternDoesNotFit()
    {
        $request = $this->createMockRequest('GET', '/home', 'otherdomain.com');
        $matcher = new ExactRequestMatcher('GET', '/home', '%.mydomain.com');

        $this->assertFalse($matcher->matches($request));
    }

    public function testMatchesIsCaseInsensitive()
    {
        $request = $this->createMockRequest('GET', '/home', 'MyDomain.COM');
        $matcher = new ExactRequestMatcher('GET', '/home', 'mydomain.com');

        $this->assertTrue($matcher->matches($request));
    }

    public function testMatchesStripsPortFromRequestDomain()
    {
        $request = $this->createMockRequest('GET', '/home', 'mydomain.com:8080');
        $matcher = new ExactRequestMatcher('GET', '/home', 'mydomain.com');

        $this->assertTrue($matcher->matches($request));
    }

    public function testMatchesAnyDomainWhenDomainIsNull()
    {
        $request = $this->createMockRequest('GET', '/home', 'anydomain.com');
        $matcher = new ExactRequestMatcher('GET', '/home', null);

        $this->assertTrue($matcher->matches($request));
    }

    private function createMockRequest($method, $url, $domain)
    {
        $mock = $this->createMock(Request::class);
        $mock->method('requestMethod')->willReturn($method);
        $mock->method('requestPath')->willReturn($url);
        $mock->method('domain')->willReturn($domain);
        return $mock;
    }
}
