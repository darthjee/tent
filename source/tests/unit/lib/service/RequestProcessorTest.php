<?php

namespace Tent\Tests\Service;

use PHPUnit\Framework\TestCase;
use Tent\Service\RequestProcessor;
use Tent\Configuration;
use Tent\Models\Rule;
use Tent\RequestHandlers\ProxyRequestHandler;
use Tent\RequestHandlers\StaticFileHandler;
use Tent\Models\FolderLocation;
use Tent\Models\Request;
use Tent\Models\Response;
use Tent\Matchers\RequestMatcher;
use Tent\Models\Server;

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
            'requestPath' => '/index.html',
            'requestMethod' => 'GET'
        ]);
        $response = RequestProcessor::handleRequest($request);

        $expectedContent = file_get_contents($this->staticPath . '/index.html');
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->httpCode());
        $this->assertEquals($expectedContent, $response->body());
        $this->assertStringContainsString('Content-Type: text/html', implode("\n", $response->headers()));
    }

    public function testProxyRequestHandlerForwardsToHttpbin()
    {
        $request = new Request([
            'requestPath' => '/get',
            'requestMethod' => 'GET',
            'query' => '',
            'headers' => []
        ]);
        $response = RequestProcessor::handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->httpCode());
        $this->assertNotEmpty($response->body());
        // httpbin returns JSON for /anything and /get endpoints, so we check for JSON
        $json = json_decode($response->body(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('url', $json);
        $this->assertStringContainsString('/get', $json['url']);
    }

    public function testReturnsMissingResponseForUnmatchedRoute()
    {
        // No rules added, so fallback handler should be used
        $request = new Request([
            'requestPath' => '/other',
            'requestMethod' => 'GET'
        ]);
        $response = RequestProcessor::handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(404, $response->httpCode());
        $this->assertStringContainsString('Not Found', $response->body());
    }
}
