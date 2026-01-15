<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\RequestProcessor;
use Tent\Configuration;
use Tent\Rule;
use Tent\Handlers\ProxyRequestHandler;
use Tent\Handlers\StaticFileHandler;
use Tent\FolderLocation;
use Tent\Request;
use Tent\Response;
use Tent\RequestMatcher;
use Tent\Server;

class RequestProcessorTest extends TestCase
{
    private $staticPath;

    protected function setupStatic()
    {
        $this->staticPath = __DIR__ . '/../../../fixtures/static';
        $staticLocation = new FolderLocation($this->staticPath);

        Configuration::addRule(
            new Rule(new StaticFileHandler($staticLocation), [
                new RequestMatcher('GET', '/index.html', 'exact')
            ])
        );
    }

    protected function setupProxy()
    {
        $server = new Server('http://httpbin');

        Configuration::addRule(
            new Rule(new ProxyRequestHandler($server), [
                new RequestMatcher('GET', '/get', 'exact')
            ])
        );
    }

    protected function setUp(): void
    {
        // Reset rules before each test
        Configuration::reset();
        $this->setupStatic();
        $this->setupProxy();
    }

    public function testStaticFileHandlerReturnsIndexHtml()
    {

        // Create a request to /index.html using named parameters
        $request = new Request([
            'requestUrl' => '/index.html',
            'requestMethod' => 'GET'
        ]);
        $response = RequestProcessor::handleRequest($request);

        $expectedContent = file_get_contents($this->staticPath . '/index.html');
        $this->assertInstanceOf(\Tent\Response::class, $response);
        $this->assertEquals(200, $response->httpCode);
        $this->assertEquals($expectedContent, $response->body);
        $this->assertStringContainsString('Content-Type: text/html', implode("\n", $response->headerLines));
    }

    public function testProxyRequestHandlerForwardsToHttpbin()
    {
        $request = new Request([
            'requestUrl' => '/get',
            'requestMethod' => 'GET',
            'query' => '',
            'headers' => []
        ]);
        $response = RequestProcessor::handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->httpCode);
        $this->assertNotEmpty($response->body);
        // httpbin returns JSON for /anything and /get endpoints, so we check for JSON
        $json = json_decode($response->body, true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('url', $json);
        $this->assertStringContainsString('/get', $json['url']);
    }

    public function testReturnsMissingResponseForUnmatchedRoute()
    {
        // No rules added, so fallback handler should be used
        $request = new \Tent\Request([
            'requestUrl' => '/other',
            'requestMethod' => 'GET'
        ]);
        $response = \Tent\RequestProcessor::handleRequest($request);

        $this->assertInstanceOf(\Tent\Response::class, $response);
        $this->assertEquals(404, $response->httpCode);
        $this->assertStringContainsString('Not Found', $response->body);
    }
}
