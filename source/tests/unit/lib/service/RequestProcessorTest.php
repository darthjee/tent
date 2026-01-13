<?php

namespace Tent\Tests;

require_once __DIR__ . '/../../../support/tests_loader.php';

use PHPUnit\Framework\TestCase;
use Tent\RequestProcessor;
use Tent\Configuration;
use Tent\Rule;

use Tent\ProxyRequestHandler;
use Tent\StaticFileHandler;
use Tent\FolderLocation;
use Tent\Request;
use Tent\Response;
use Tent\RequestMatcher;
use Tent\Server;
use Tent\CurlHttpClient;

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
        // Setup ProxyRequestHandler to httpbin
        $server = new Server('http://httpbin');
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
}
