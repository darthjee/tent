<?php

namespace Tent\Tests\Support\Handlers;

use Tent\Cache\RequestHasher;
use Tent\Content\FileCache;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Middlewares\CacheStalenessMiddleware;
use Tent\Middlewares\FileCacheMiddleware;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;
use Tent\RequestHandlers\ProxyRequestHandler;
use Tent\RequestHandlers\RequestHandler;
use Tent\Tests\Support\Http\StubHttpClient;
use Tent\Tests\Support\Utils\FileSystemUtils;

/**
 * Shared setup for tests driving `CacheStalenessMiddleware` through a real handler,
 * with an entry on disk whose age is controlled.
 *
 * Refreshes scheduled by the handler are counted in `$scheduled` and run immediately,
 * against a stubbed upstream.
 */
trait StaleCacheScenario
{
    private string $cacheDir;

    private int $scheduled = 0;

    /**
     * @var array Result of the upstream request issued by a refresh.
     */
    private array $refreshResult = ['body' => 'fresh body', 'httpCode' => 200, 'headers' => []];

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/staleness_handler_test_' . uniqid();
        mkdir($this->cacheDir);
        Logger::setInstance(new NullLoggerInstance());
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->cacheDir);
        Logger::setInstance(new LoggerInstance());
    }

    private function fileCacheConfig(array $extra = []): array
    {
        return array_merge([
            'class' => FileCacheMiddleware::class,
            'location' => $this->cacheDir,
        ], $extra);
    }

    private function stalenessConfig(array $extra = []): array
    {
        return array_merge([
            'class' => CacheStalenessMiddleware::class,
            'location' => $this->cacheDir,
            'host' => 'http://backend:8080',
            'maxAgeSeconds' => 300,
        ], $extra);
    }

    /**
     * Builds a handler from its configuration, stubbing its upstream and every
     * staleness middleware.
     */
    private function buildHandler(array $params, ?array $upstreamResult = null): RequestHandler
    {
        $handler = RequestHandler::build($params);

        $httpClient = $this->createMock(\Tent\Http\HttpClientInterface::class);

        if ($upstreamResult === null) {
            $httpClient->expects($this->never())->method('request');
        } else {
            $httpClient->expects($this->once())->method('request')->willReturn($upstreamResult);
        }

        $this->setProperty(ProxyRequestHandler::class, $handler, 'httpClient', $httpClient);

        $middlewares = (new \ReflectionProperty(RequestHandler::class, 'middlewares'));
        $middlewares->setAccessible(true);

        foreach ($middlewares->getValue($handler) as $middleware) {
            if ($middleware instanceof CacheStalenessMiddleware) {
                $this->stubStaleness($middleware);
            }
        }

        return $handler;
    }

    private function stubStaleness(CacheStalenessMiddleware $middleware): void
    {
        $this->setProperty(
            CacheStalenessMiddleware::class,
            $middleware,
            'httpClient',
            new StubHttpClient($this->refreshResult)
        );
        $this->setProperty(
            CacheStalenessMiddleware::class,
            $middleware,
            'scheduler',
            function (callable $task) {
                $this->scheduled++;
                $task();
            }
        );
    }

    private function setProperty(string $class, object $object, string $name, mixed $value): void
    {
        $property = new \ReflectionProperty($class, $name);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    private function buildRequest(): ProcessingRequest
    {
        return new ProcessingRequest([
            'requestMethod' => 'GET',
            'requestPath' => '/users',
            'headers' => [],
            'query' => '',
        ]);
    }

    /**
     * Returns the entry of the request, through a request of its own, so that nothing
     * is memoized on the one being handled.
     */
    private function entry(?RequestHasher $hasher = null): FileCache
    {
        return new FileCache($this->buildRequest(), new FolderLocation($this->cacheDir), $hasher);
    }

    private function storeEntry(int $age, int $httpCode = 200, ?RequestHasher $hasher = null): void
    {
        $cache = $this->entry($hasher);
        $cache->store(new Response([
            'body' => 'cached body',
            'httpCode' => $httpCode,
            'headers' => ['Content-Type: text/plain'],
        ]));

        $meta = json_decode(file_get_contents($cache->metaFilePath()), true);
        $meta['timestamp'] = time() - $age;
        file_put_contents($cache->metaFilePath(), json_encode($meta));
    }
}
