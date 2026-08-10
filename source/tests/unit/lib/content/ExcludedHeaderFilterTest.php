<?php

namespace Tent\Tests\Content;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\ExcludedHeaderFilter;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Models\Response;

class ExcludedHeaderFilterTest extends TestCase
{
    protected function setUp(): void
    {
        Logger::setInstance(new NullLoggerInstance());
    }

    protected function tearDown(): void
    {
        Logger::setInstance(new LoggerInstance());
    }

    public function testFilterStripsDefaultExcludedHeaders()
    {
        $filter = new ExcludedHeaderFilter(ExcludedHeaderFilter::DEFAULT_EXCLUDED_HEADERS);
        $response = $this->buildResponse([
            'Content-Type: text/plain',
            'Set-Cookie: session=abc',
            'Set-Cookie2: legacy=abc',
            'WWW-Authenticate: Basic realm="x"',
            'Proxy-Authenticate: Basic realm="y"',
        ]);

        $filtered = $filter->filter($response);

        $this->assertEquals(['Content-Type: text/plain'], $filtered->headers());
    }

    public function testFilterCanBeOverriddenWithCustomList()
    {
        $filter = new ExcludedHeaderFilter(['X-Custom']);
        $response = $this->buildResponse(['Content-Type: text/plain', 'X-Custom: 1', 'Set-Cookie: a=1']);

        $filtered = $filter->filter($response);

        $this->assertEquals(['Content-Type: text/plain', 'Set-Cookie: a=1'], $filtered->headers());
    }

    public function testFilterStripsAllOccurrencesOfSameNameHeader()
    {
        $filter = new ExcludedHeaderFilter(['Set-Cookie']);
        $response = $this->buildResponse(['Set-Cookie: a=1', 'Set-Cookie: b=2', 'Content-Type: text/plain']);

        $filtered = $filter->filter($response);

        $this->assertEquals(['Content-Type: text/plain'], $filtered->headers());
    }

    public function testFilterSplitsOnFirstColonOnlyLeavingValueColonsIntact()
    {
        $filter = new ExcludedHeaderFilter(['Expires']);
        $response = $this->buildResponse(['Expires: Wed, 21 Oct 2026 07:28:00 GMT', 'Content-Type: text/plain']);

        $filtered = $filter->filter($response);

        $this->assertEquals(['Content-Type: text/plain'], $filtered->headers());
    }

    public function testFilterMatchesCaseInsensitively()
    {
        $filter = new ExcludedHeaderFilter(['set-cookie']);
        $response = $this->buildResponse(['Set-Cookie: a=1', 'Content-Type: text/plain']);

        $filtered = $filter->filter($response);

        $this->assertEquals(['Content-Type: text/plain'], $filtered->headers());
    }

    public function testFilterMatchesExactNameOnlyNotSubstring()
    {
        $filter = new ExcludedHeaderFilter(['Set-Cookie']);
        $response = $this->buildResponse(['X-Original-Set-Cookie: a=1', 'Content-Type: text/plain']);

        $filtered = $filter->filter($response);

        $this->assertEquals(['X-Original-Set-Cookie: a=1', 'Content-Type: text/plain'], $filtered->headers());
    }

    public function testFilterWithEmptyListStripsNothing()
    {
        $filter = new ExcludedHeaderFilter([]);
        $response = $this->buildResponse(['Set-Cookie: a=1', 'Content-Type: text/plain']);

        $filtered = $filter->filter($response);

        $this->assertEquals(['Set-Cookie: a=1', 'Content-Type: text/plain'], $filtered->headers());
    }

    public function testFilterDoesNotMutateBodyHttpCodeOrRequest()
    {
        $filter = new ExcludedHeaderFilter(['Set-Cookie']);
        $response = $this->buildResponse(['Set-Cookie: a=1'], 201, 'the body');

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

        $filter = new ExcludedHeaderFilter(['Set-Cookie']);
        $response = $this->buildResponse(['Set-Cookie: a=1', 'Content-Type: text/plain']);

        $filter->filter($response);
    }

    public function testFilterDoesNotLogWhenNothingStripped()
    {
        $logger = $this->createMock(LoggerInstance::class);
        $logger->expects($this->never())->method('log');
        Logger::setInstance($logger);

        $filter = new ExcludedHeaderFilter(['Set-Cookie']);
        $response = $this->buildResponse(['Content-Type: text/plain']);

        $filter->filter($response);
    }

    private function buildResponse(array $headers, int $httpCode = 200, string $body = ''): Response
    {
        return new Response(['body' => $body, 'httpCode' => $httpCode, 'headers' => $headers]);
    }
}
