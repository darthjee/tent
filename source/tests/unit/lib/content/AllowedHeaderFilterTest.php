<?php

namespace Tent\Tests\Content;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\AllowedHeaderFilter;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Models\Response;

class AllowedHeaderFilterTest extends TestCase
{
    protected function setUp(): void
    {
        Logger::setInstance(new NullLoggerInstance());
    }

    protected function tearDown(): void
    {
        Logger::setInstance(new LoggerInstance());
    }

    public function testFilterKeepsOnlyAllowedHeaders()
    {
        $filter = new AllowedHeaderFilter(['Content-Type']);
        $response = $this->buildResponse(['Content-Type: text/plain', 'Set-Cookie: a=1']);

        $filtered = $filter->filter($response);

        $this->assertEquals(['Content-Type: text/plain'], $filtered->headers());
    }

    public function testFilterKeepsAllOccurrencesOfSameNameAllowedHeader()
    {
        $filter = new AllowedHeaderFilter(['Set-Cookie']);
        $response = $this->buildResponse(['Set-Cookie: a=1', 'Set-Cookie: b=2', 'Content-Type: text/plain']);

        $filtered = $filter->filter($response);

        $this->assertEquals(['Set-Cookie: a=1', 'Set-Cookie: b=2'], $filtered->headers());
    }

    public function testFilterSplitsOnFirstColonOnlyLeavingValueColonsIntact()
    {
        $filter = new AllowedHeaderFilter(['Expires']);
        $response = $this->buildResponse(['Expires: Wed, 21 Oct 2026 07:28:00 GMT', 'Content-Type: text/plain']);

        $filtered = $filter->filter($response);

        $this->assertEquals(['Expires: Wed, 21 Oct 2026 07:28:00 GMT'], $filtered->headers());
    }

    public function testFilterMatchesCaseInsensitively()
    {
        $filter = new AllowedHeaderFilter(['content-type']);
        $response = $this->buildResponse(['Content-Type: text/plain', 'Set-Cookie: a=1']);

        $filtered = $filter->filter($response);

        $this->assertEquals(['Content-Type: text/plain'], $filtered->headers());
    }

    public function testFilterMatchesExactNameOnlyNotSubstring()
    {
        $filter = new AllowedHeaderFilter(['Content-Type']);
        $response = $this->buildResponse(['X-Content-Type-Options: nosniff', 'Content-Type: text/plain']);

        $filtered = $filter->filter($response);

        $this->assertEquals(['Content-Type: text/plain'], $filtered->headers());
    }

    public function testFilterWithEmptyListStripsEverything()
    {
        $filter = new AllowedHeaderFilter([]);
        $response = $this->buildResponse(['Set-Cookie: a=1', 'Content-Type: text/plain']);

        $filtered = $filter->filter($response);

        $this->assertEquals([], $filtered->headers());
    }

    public function testFilterDoesNotMutateBodyHttpCodeOrRequest()
    {
        $filter = new AllowedHeaderFilter(['Content-Type']);
        $response = $this->buildResponse(['Content-Type: text/plain'], 201, 'the body');

        $filtered = $filter->filter($response);

        $this->assertEquals('the body', $filtered->body());
        $this->assertEquals(201, $filtered->httpCode());
        $this->assertNotSame($response, $filtered);
    }

    public function testFilterLogsDebugWithStrippedHeaderNamesWhenSomethingStripped()
    {
        $logger = $this->createMock(LoggerInstance::class);
        $logger->expects($this->once())
            ->method('log')
            ->with($this->stringContains('Set-Cookie'), 'debug');
        Logger::setInstance($logger);

        $filter = new AllowedHeaderFilter(['Content-Type']);
        $response = $this->buildResponse(['Content-Type: text/plain', 'Set-Cookie: a=1']);

        $filter->filter($response);
    }

    public function testFilterDoesNotLogWhenNothingStripped()
    {
        $logger = $this->createMock(LoggerInstance::class);
        $logger->expects($this->never())->method('log');
        Logger::setInstance($logger);

        $filter = new AllowedHeaderFilter(['Content-Type']);
        $response = $this->buildResponse(['Content-Type: text/plain']);

        $filter->filter($response);
    }

    private function buildResponse(array $headers, int $httpCode = 200, string $body = ''): Response
    {
        return new Response(['body' => $body, 'httpCode' => $httpCode, 'headers' => $headers]);
    }
}
