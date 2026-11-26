<?php

namespace Tent\Tests\RequestHandlers\DefaultProxyRequestHandler;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\RequestHandlers\RequestHandler;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;
use Tent\Models\FolderLocation;
use Tent\Content\FileCache;
use Tent\Http\HttpClientInterface;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Tests\Support\Middlewares\SkipCacheMiddleware;
use Tent\Tests\Support\Utils\FileSystemUtils;

class DefaultProxyRequestHandlerSkipCacheTest extends TestCase
{
    private string $cacheDir;
    private ProcessingRequest $request;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/default_proxy_skip_cache_test_' . uniqid();
        mkdir($this->cacheDir);
        Logger::setInstance(new NullLoggerInstance());
        $this->request = new ProcessingRequest([
            'requestMethod' => 'GET',
            'headers' => [],
            'requestPath' => '/api/users',
            'query' => '',
        ]);
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->cacheDir);
        Logger::setInstance(new LoggerInstance());
    }

    public function testPrependedGuardBypassesCacheRead()
    {
        $this->buildCache();
        $client = $this->clientExpecting($this->once());

        $handler = $this->buildHandler('prependMiddlewares', $client);
        $response = $handler->handleRequest($this->request);

        $this->assertSame('upstream body', $response->body());
    }

    public function testPrependedGuardBypassesCacheWrite()
    {
        $client = $this->clientExpecting($this->once());

        $this->buildHandler('prependMiddlewares', $client)->handleRequest($this->request);

        $this->assertFalse((new FileCache($this->request, new FolderLocation($this->cacheDir)))->exists());
    }

    public function testPrependedGuardAddsNoMarkerHeader()
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'http://backend:8080/api/users',
                $this->identicalTo(['Host' => 'backend:8080']),
                $this->anything(),
                $this->anything(),
                $this->anything()
            )
            ->willReturn(['body' => 'upstream body', 'httpCode' => 200, 'headers' => ['Content-Type: text/plain']]);

        $response = $this->buildHandler('prependMiddlewares', $client)->handleRequest($this->request);

        $this->assertSame(['Content-Type: text/plain'], $response->headers());
    }

    public function testGuardInMiddlewaresStillServesFromCache()
    {
        $this->buildCache();
        $client = $this->clientExpecting($this->never());

        $response = $this->buildHandler('middlewares', $client)->handleRequest($this->request);

        $this->assertSame('cached body', $response->body());
    }

    public function testGuardInMiddlewaresBypassesCacheWrite()
    {
        $client = $this->clientExpecting($this->once());

        $this->buildHandler('middlewares', $client)->handleRequest($this->request);

        $this->assertFalse((new FileCache($this->request, new FolderLocation($this->cacheDir)))->exists());
    }

    private function clientExpecting($matcher): HttpClientInterface
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects($matcher)
            ->method('request')
            ->willReturn(['body' => 'upstream body', 'httpCode' => 200, 'headers' => ['Content-Type: text/plain']]);
        return $client;
    }

    private function buildHandler(string $placement, HttpClientInterface $client): RequestHandler
    {
        $handler = RequestHandler::build([
            'type' => 'default_proxy',
            'host' => 'http://backend:8080',
            'cache' => $this->cacheDir,
            'cacheCodes' => ['2xx'],
            $placement => [['class' => SkipCacheMiddleware::class]],
        ]);

        $property = (new \ReflectionClass($handler))->getParentClass()->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($handler, $client);

        return $handler;
    }

    private function buildCache(): void
    {
        $response = new Response([
            'body' => 'cached body',
            'httpCode' => 200,
            'headers' => ['Content-Type: text/plain'],
            'request' => $this->request,
        ]);
        (new FileCache($this->request, new FolderLocation($this->cacheDir)))->store($response);
    }
}
