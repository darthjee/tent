<?php

namespace Tent\Tests\Middlewares\CacheCleanupMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\CacheCleanupMiddleware;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Models\ProcessingRequest;
use Tent\Tests\Support\Utils\FileSystemUtils;

class CacheCleanupMiddlewareProcessRequestTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/cache_cleanup_test_' . uniqid();
        mkdir($this->cacheDir);
        Logger::setInstance(new NullLoggerInstance());
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->cacheDir);
        Logger::setInstance(new LoggerInstance());
    }

    public function testGetRequestDoesNotDeleteAnything()
    {
        $collectionDir = $this->cacheDir . '/users/GET';
        mkdir($collectionDir, 0777, true);

        $middleware = $this->buildMiddleware();
        $middleware->processRequest($this->buildRequest('/users', 'GET'));

        $this->assertDirectoryExists($collectionDir);
    }

    public function testPostDeletesCollectionDir()
    {
        $collectionDir = $this->cacheDir . '/users/GET';
        mkdir($collectionDir, 0777, true);

        $middleware = $this->buildMiddleware();
        $middleware->processRequest($this->buildRequest('/users', 'POST'));

        $this->assertDirectoryDoesNotExist($collectionDir);
    }

    public function testPostDoesNotDeleteEntityDir()
    {
        $entityDir = $this->cacheDir . '/users/1/GET';
        mkdir($entityDir, 0777, true);

        $middleware = $this->buildMiddleware();
        $middleware->processRequest($this->buildRequest('/users', 'POST'));

        $this->assertDirectoryExists($entityDir);
    }

    public function testPatchDeletesCollectionAndEntityDir()
    {
        $collectionDir = $this->cacheDir . '/users/GET';
        $entityDir     = $this->cacheDir . '/users/1/GET';
        mkdir($collectionDir, 0777, true);
        mkdir($entityDir, 0777, true);

        $middleware = $this->buildMiddleware();
        $middleware->processRequest($this->buildRequest('/users/1', 'PATCH'));

        $this->assertDirectoryDoesNotExist($collectionDir);
        $this->assertDirectoryDoesNotExist($entityDir);
    }

    public function testPutDeletesCollectionAndEntityDir()
    {
        $collectionDir = $this->cacheDir . '/users/GET';
        $entityDir     = $this->cacheDir . '/users/1/GET';
        mkdir($collectionDir, 0777, true);
        mkdir($entityDir, 0777, true);

        $middleware = $this->buildMiddleware();
        $middleware->processRequest($this->buildRequest('/users/1', 'PUT'));

        $this->assertDirectoryDoesNotExist($collectionDir);
        $this->assertDirectoryDoesNotExist($entityDir);
    }

    public function testDeleteDeletesCollectionAndEntityDir()
    {
        $collectionDir = $this->cacheDir . '/users/GET';
        $entityDir     = $this->cacheDir . '/users/1/GET';
        mkdir($collectionDir, 0777, true);
        mkdir($entityDir, 0777, true);

        $middleware = $this->buildMiddleware();
        $middleware->processRequest($this->buildRequest('/users/1', 'DELETE'));

        $this->assertDirectoryDoesNotExist($collectionDir);
        $this->assertDirectoryDoesNotExist($entityDir);
    }

    public function testCustomClearEntityOnlyOnPost()
    {
        $collectionDir = $this->cacheDir . '/users/GET';
        $entityDir     = $this->cacheDir . '/users/1/GET';
        mkdir($collectionDir, 0777, true);
        mkdir($entityDir, 0777, true);

        $middleware = $this->buildMiddleware(['clear' => ['entity']]);
        $middleware->processRequest($this->buildRequest('/users/1', 'POST'));

        $this->assertDirectoryExists($collectionDir);
        $this->assertDirectoryDoesNotExist($entityDir);
    }

    public function testNonExistentDirDoesNotThrow()
    {
        $middleware = $this->buildMiddleware();

        $this->expectNotToPerformAssertions();
        $middleware->processRequest($this->buildRequest('/users', 'POST'));
    }

    public function testUnrelatedCacheDirIsNotDeleted()
    {
        $unrelatedDir = $this->cacheDir . '/products/GET';
        $collectionDir = $this->cacheDir . '/users/GET';
        mkdir($unrelatedDir, 0777, true);
        mkdir($collectionDir, 0777, true);

        $middleware = $this->buildMiddleware();
        $middleware->processRequest($this->buildRequest('/users', 'POST'));

        $this->assertDirectoryExists($unrelatedDir);
        $this->assertDirectoryDoesNotExist($collectionDir);
    }

    public function testRequestReturnedUnchanged()
    {
        $middleware = $this->buildMiddleware();
        $request = $this->buildRequest('/users', 'POST');

        $result = $middleware->processRequest($request);

        $this->assertSame($request, $result);
    }

    public function testLoggerCalledForEachDeletedDir()
    {
        $collectionDir = $this->cacheDir . '/users/GET';
        $entityDir     = $this->cacheDir . '/users/1/GET';
        mkdir($collectionDir, 0777, true);
        mkdir($entityDir, 0777, true);

        $instance = $this->createMock(LoggerInstance::class);
        $instance->expects($this->exactly(2))
            ->method('log')
            ->with($this->stringContains('cache cleared'), 'debug');
        Logger::setInstance($instance);

        $middleware = $this->buildMiddleware();
        $middleware->processRequest($this->buildRequest('/users/1', 'DELETE'));
    }

    public function testEntityTargetSkippedForSingleSegmentPath()
    {
        $collectionDir = $this->cacheDir . '/users/GET';
        mkdir($collectionDir, 0777, true);

        $middleware = $this->buildMiddleware(['clear' => ['entity']]);
        $middleware->processRequest($this->buildRequest('/users', 'DELETE'));

        $this->assertDirectoryExists($collectionDir);
    }

    private function buildRequest(string $path, string $method): ProcessingRequest
    {
        return new ProcessingRequest([
            'requestPath'   => $path,
            'requestMethod' => $method,
        ]);
    }

    private function buildMiddleware(array $extra = []): CacheCleanupMiddleware
    {
        return CacheCleanupMiddleware::build(array_merge(
            ['location' => $this->cacheDir],
            $extra
        ));
    }
}
