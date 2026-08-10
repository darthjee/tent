<?php

namespace Tent\Tests\Content;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\AllowedHeaderFilter;
use Tent\Content\ExcludedHeaderFilter;
use Tent\Content\HeaderFilterBuilder;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Models\Response;

class HeaderFilterBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        Logger::setInstance(new NullLoggerInstance());
    }

    protected function tearDown(): void
    {
        Logger::setInstance(new LoggerInstance());
    }

    public function testBuildDenyModeWithoutExcludedHeadersUsesDefaultList()
    {
        $filter = HeaderFilterBuilder::build('deny', null, null, null);

        $this->assertInstanceOf(ExcludedHeaderFilter::class, $filter);
        $response = new Response(['headers' => ['Set-Cookie: a=1', 'Content-Type: text/plain']]);
        $this->assertEquals(['Content-Type: text/plain'], $filter->filter($response)->headers());
    }

    public function testBuildDenyModeWithExcludedHeadersOverridesDefaultList()
    {
        $filter = HeaderFilterBuilder::build('deny', ['X-Custom'], null, null);

        $response = new Response(['headers' => ['Set-Cookie: a=1', 'X-Custom: 1', 'Content-Type: text/plain']]);
        $this->assertEquals(
            ['Set-Cookie: a=1', 'Content-Type: text/plain'],
            $filter->filter($response)->headers()
        );
    }

    public function testBuildDenyModeMergesAdditionalExcludedHeadersOnTopOfDefault()
    {
        $filter = HeaderFilterBuilder::build('deny', null, ['X-Custom'], null);

        $response = new Response(['headers' => ['Set-Cookie: a=1', 'X-Custom: 1', 'Content-Type: text/plain']]);
        $this->assertEquals(['Content-Type: text/plain'], $filter->filter($response)->headers());
    }

    public function testBuildDenyModeMergesAdditionalExcludedHeadersOnTopOfExplicitList()
    {
        $filter = HeaderFilterBuilder::build('deny', ['X-One'], ['X-Two'], null);

        $response = new Response(['headers' => ['X-One: 1', 'X-Two: 2', 'Content-Type: text/plain']]);
        $this->assertEquals(['Content-Type: text/plain'], $filter->filter($response)->headers());
    }

    public function testBuildAllowModeWithValidListReturnsAllowedHeaderFilter()
    {
        $filter = HeaderFilterBuilder::build('allow', null, null, ['Content-Type']);

        $this->assertInstanceOf(AllowedHeaderFilter::class, $filter);
        $response = new Response(['headers' => ['Set-Cookie: a=1', 'Content-Type: text/plain']]);
        $this->assertEquals(['Content-Type: text/plain'], $filter->filter($response)->headers());
    }

    public function testBuildAllowModeWithEmptyListThrows()
    {
        $this->expectException(\InvalidArgumentException::class);

        HeaderFilterBuilder::build('allow', null, null, []);
    }

    public function testBuildAllowModeWithMissingListThrows()
    {
        $this->expectException(\InvalidArgumentException::class);

        HeaderFilterBuilder::build('allow', null, null, null);
    }

    public function testBuildWithInvalidModeThrows()
    {
        $this->expectException(\InvalidArgumentException::class);

        HeaderFilterBuilder::build('invalid', null, null, null);
    }
}
