<?php

namespace Tent\Tests\RequestHandlers\DefaultProxyRequestHandler;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\RequestHandlers\DefaultProxyRequestHandler;
use Tent\RequestHandlers\ProxyRequestHandler;
use Tent\Middlewares\RenameHeaderMiddleware;
use Tent\Middlewares\SetHeadersMiddleware;
use Tent\Middlewares\FileCacheMiddleware;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;
use Tent\Http\HttpClientInterface;

class DefaultProxyRequestHandlerTest extends TestCase
{
    public function testExtendsProxyRequestHandler()
    {
        $handler = new DefaultProxyRequestHandler('http://api:80', false);
        $this->assertInstanceOf(ProxyRequestHandler::class, $handler);
    }

    public function testMiddlewareOrderWithCacheEnabled()
    {
        $handler = new DefaultProxyRequestHandler('http://api:80', './cache', ['2xx']);

        $reflection = new \ReflectionClass($handler);
        $prop = $reflection->getProperty('middlewares');
        $prop->setAccessible(true);
        $middlewares = $prop->getValue($handler);

        $this->assertCount(3, $middlewares);
        $this->assertInstanceOf(RenameHeaderMiddleware::class, $middlewares[0]);
        $this->assertInstanceOf(SetHeadersMiddleware::class, $middlewares[1]);
        $this->assertInstanceOf(FileCacheMiddleware::class, $middlewares[2]);
    }

    public function testMiddlewareOrderWithCacheDisabled()
    {
        $handler = new DefaultProxyRequestHandler('http://api:80', false);

        $reflection = new \ReflectionClass($handler);
        $prop = $reflection->getProperty('middlewares');
        $prop->setAccessible(true);
        $middlewares = $prop->getValue($handler);

        $this->assertCount(2, $middlewares);
        $this->assertInstanceOf(RenameHeaderMiddleware::class, $middlewares[0]);
        $this->assertInstanceOf(SetHeadersMiddleware::class, $middlewares[1]);
    }

    public function testDefaultCacheDirectory()
    {
        $handler = new DefaultProxyRequestHandler('http://api:80');

        $reflection = new \ReflectionClass($handler);
        $prop = $reflection->getProperty('middlewares');
        $prop->setAccessible(true);
        $middlewares = $prop->getValue($handler);

        // With defaults, caching should be enabled (3 middlewares)
        $this->assertCount(3, $middlewares);
        $this->assertInstanceOf(FileCacheMiddleware::class, $middlewares[2]);
    }

    public function testHostIsRenamedToXForwardedHost()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'http://api:80/test',
                ['X-Forwarded-Host' => 'original.host', 'Host' => 'http://api:80'],
                null
            )
            ->willReturn(['body' => '', 'httpCode' => 200, 'headers' => []]);

        // We need to inject a mock http client; use reflection
        $handler = new DefaultProxyRequestHandler('http://api:80', false, [], $httpClient);

        $request = new ProcessingRequest([
            'requestMethod' => 'GET',
            'requestPath'   => '/test',
            'query'         => '',
            'headers'       => ['Host' => 'original.host'],
        ]);

        $response = $handler->handleRequest($request);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testHostIsSetToConstructorValue()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'http://api:80/test',
                $this->callback(function ($headers) {
                    return isset($headers['Host'])
                        && $headers['Host'] === 'http://api:80';
                })
            )
            ->willReturn(['body' => '', 'httpCode' => 200, 'headers' => []]);

        $handler = new DefaultProxyRequestHandler('http://api:80', false, [], $httpClient);

        $request = new ProcessingRequest([
            'requestMethod' => 'GET',
            'requestPath'   => '/test',
            'query'         => '',
            'headers'       => ['Host' => 'original.host'],
        ]);

        $response = $handler->handleRequest($request);
        $this->assertInstanceOf(Response::class, $response);
    }
}
