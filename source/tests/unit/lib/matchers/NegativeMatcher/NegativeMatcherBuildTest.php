<?php

namespace Tent\Tests\Matchers;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\NegativeMatcher;
use Tent\Models\Response;

class NegativeMatcherBuildTest extends TestCase
{
    private function mockResponse($code)
    {
        return new Response(['httpCode' => $code]);
    }

    public function testBuildCreatesNegativeMatcherWrappingGivenMatcher()
    {
        $matcher = NegativeMatcher::build([
            'matcher' => [
                'class' => 'Tent\Matchers\StatusCodeMatcher',
                'httpCodes' => [200]
            ]
        ]);

        $this->assertFalse($matcher->matchResponse($this->mockResponse(200)));
        $this->assertTrue($matcher->matchResponse($this->mockResponse(404)));
    }
}
