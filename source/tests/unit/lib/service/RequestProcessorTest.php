<?php

require_once __DIR__ . '/../../../../source/lib/service/RequestProcessor.php';
require_once __DIR__ . '/../../../../source/lib/Configuration.php';
require_once __DIR__ . '/../../../../source/lib/handlers/MissingRequestHandler.php';

use PHPUnit\Framework\TestCase;
use Tent\RequestProcessor;
use Tent\Configuration;
use Tent\Rule;

class DummyRequestHandler {
    public $handledRequest = null;
    public function handleRequest($request) {
        $this->handledRequest = $request;
        return "handled: " . $request;
    }
}

class DummyRule {
    private $shouldMatch;
    private $handler;
    public function __construct($shouldMatch, $handler) {
        $this->shouldMatch = $shouldMatch;
        $this->handler = $handler;
    }
    public function match($request) {
        return $this->shouldMatch;
    }
    public function handler() {
        return $this->handler;
    }
}

require_once __DIR__ . '/../../../../source/lib/handlers/StaticFileHandler.php';
require_once __DIR__ . '/../../../../source/lib/handlers/ProxyRequestHandler.php';
require_once __DIR__ . '/../../../../source/lib/models/FolderLocation.php';
require_once __DIR__ . '/../../../../source/lib/models/Request.php';
require_once __DIR__ . '/../../../../source/lib/models/Response.php';
require_once __DIR__ . '/../../../../source/lib/models/RequestMatcher.php';
require_once __DIR__ . '/../../../../source/lib/models/Server.php';

require_once __DIR__ . '/../../../../source/lib/handlers/ProxyRequestHandler.php';
require_once __DIR__ . '/../../../../source/lib/models/Server.php';
require_once __DIR__ . '/../../../../source/lib/http/CurlHttpClient.php';

use Tent\ProxyRequestHandler;
use Tent\StaticFileHandler;
use Tent\FolderLocation;
use Tent\Request;
use Tent\Response;
use Tent\RequestMatcher;
use Tent\Server;
use Tent\CurlHttpClient;

class RequestProcessorTest extends TestCase {
    private $staticPath;
    
    protected function setupStatic() {
        $this->staticPath = __DIR__ . '/../../../fixtures/static';
        $staticLocation = new FolderLocation($this->staticPath);

        Configuration::addRule(
            new Rule(new StaticFileHandler($staticLocation), [
                new RequestMatcher('GET', '/index.html', 'exact')
            ])
        );
    }
    
    protected function setupProxy() {
        $server = new Server('http://httpbin');

        Configuration::addRule(
            new Rule(new ProxyRequestHandler($server), [
                new RequestMatcher('GET', '/get', 'exact')
            ])
        );
    }

    protected function setUp(): void {
        // Reset rules before each test
        Configuration::reset();
        $this->setupStatic();
        $this->setupProxy();
    }

    public function testStaticFileHandlerReturnsIndexHtml() {

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

    public function testProxyRequestHandlerForwardsToHttpbin() {
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
