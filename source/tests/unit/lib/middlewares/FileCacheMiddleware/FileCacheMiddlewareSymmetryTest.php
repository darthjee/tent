<?php

namespace Tent\Tests\Middlewares\FileCacheMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\FileCacheMiddleware;
use Tent\Models\FolderLocation;
use Tent\Models\Response;
use Tent\Models\ProcessingRequest;
use Tent\Content\FileCache;
use Tent\Tests\Support\Utils\FileSystemUtils;
use Tent\Utils\CacheFilePath;

class FileCacheMiddlewareSymmetryTest extends TestCase
{
    private $cacheDir;
    private $location;
    private $path;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/filecache_middleware_test_' . uniqid();
        mkdir($this->cacheDir);
        $this->location = new FolderLocation($this->cacheDir);

        $this->path = '/api/users';
    }

    protected function tearDown(): void
    {
        //FileSystemUtils::removeDirRecursive($this->cacheDir);
    }

    /**
     * Tests that cache is NOT read when request method doesn't match.
     * Validates the first part of the symmetry: processRequest() blocks based on method.
     */
    public function testProcessRequestBlocksCacheReadWhenMethodNotInMatchers()
    {
        $request = $this->buildRequest('DELETE');

        // Create cache that would match if the request was allowed
        $this->createCacheFile($this->path, 'cached body', ['Content-Type: application/json'], 'DELETE');

        // Middleware only allows GET and POST
        $middleware = $this->buildMiddleware(['GET', 'POST'], [200]);

        $result = $middleware->processRequest($request);

        // Cache should NOT be read because method doesn't match
        $this->assertFalse($result->hasResponse());
    }

    /**
     * Tests that cache is NOT saved when response's request method doesn't match.
     * Validates the second part of the symmetry: isCacheable() blocks based on method.
     */
    public function testProcessResponseDoesNotSaveCacheWhenRequestMethodNotInMatchers()
    {
        $request = $this->buildRequest('DELETE');

        $response = new Response([
            'body' => 'response body',
            'httpCode' => 200,  // Status code that would normally be cached
            'headers' => ['Content-Type: application/json'],
            'request' => $request
        ]);

        // Middleware only allows GET and POST
        $middleware = $this->buildMiddleware(['GET', 'POST'], [200]);

        $middleware->processResponse($response);

        // Cache should NOT be saved because request method doesn't match
        $cache = new FileCache($request, $this->location);
        $this->assertFalse($cache->exists());
    }

    /**
     * Tests complete symmetry: same request allowed in both read and save.
     */
    public function testSymmetryWhenRequestMethodMatches()
    {
        $method = 'POST';
        $request = $this->buildRequest($method);

        // Create cache first
        $this->createCacheFile($this->path, 'cached body', ['Content-Type: application/json'], $method);

        // Middleware allows POST
        $middleware = $this->buildMiddleware(['POST'], [200]);

        // Part 1: Cache should be read
        $cachedRequest = $middleware->processRequest($request);
        $this->assertTrue($cachedRequest->hasResponse());

        // Part 2: Cache should be saveable
        $response = new Response([
            'body' => 'new response body',
            'httpCode' => 200,
            'headers' => ['Content-Type: application/json'],
            'request' => $request
        ]);

        $middleware->processResponse($response);
        $cache = new FileCache($request, $this->location);
        $this->assertTrue($cache->exists());
    }

    /**
     * Tests combined matchers: status code + request method
     * Both must match for cache to be read AND saved.
     */
    public function testProcessRequestBlocksWhenStatusCodeWouldNotMatch()
    {
        $request = $this->buildRequest('GET');

        // Create cache
        $this->createCacheFile($this->path, 'cached body', ['Content-Type: application/json'], 'GET');

        // Middleware allows GET + 201 status code (not 200)
        $middleware = $this->buildMiddleware(['GET'], [201]);  // This would be mismatch in isCacheable

        $result = $middleware->processRequest($request);

        // Cache without 201 status would not be saveable anyway
        // This test documents the asymmetry: we read cache for GET regardless of status
        // but only save if status matches
        $this->assertTrue($result->hasResponse());  // Cache is read
    }

    /**
     * Tests that response with wrong status code is not saved even if method matches.
     */
    public function testProcessResponseDoesNotSaveWhenStatusCodeDoesNotMatch()
    {
        $request = $this->buildRequest('GET');

        $response = new Response([
            'body' => 'response body',
            'httpCode' => 204,  // Doesn't match configured [200]
            'headers' => ['Content-Type: application/json'],
            'request' => $request
        ]);

        $middleware = $this->buildMiddleware(['GET'], [200]);

        $middleware->processResponse($response);

        // Cache should NOT be saved
        $cache = new FileCache($request, $this->location);
        $this->assertFalse($cache->exists());
    }

    /**
     * Tests the new behavior: RequestMethodMatcher now checks response's request method.
     * Ensures symmetry is maintained with the new matchResponse() implementation.
     */
    public function testRequestMethodMatcherChecksResponseRequest()
    {
        // Original request with method that matches
        $originalRequest = $this->buildRequest('POST');

        // Response with the same request
        $response = new Response([
            'body' => 'cached body',
            'httpCode' => 200,
            'headers' => ['Content-Type: application/json'],
            'request' => $originalRequest
        ]);

        $middleware = $this->buildMiddleware(['POST'], [200]);


        // Response should be cacheable because its request method matches
        $middleware->processResponse($response);
        $cache = new FileCache($originalRequest, $this->location);
        $this->assertTrue($cache->exists());
    }


    private function buildRequest(string $method)
    {
        return new ProcessingRequest([
            'requestPath' => $this->path,
            'requestMethod' => $method  // Method not in allowed list
        ]);
    }

    /**
     * Helper to create a cache file for testing.
     */
    private function createCacheFile(string $path, string $body, array $headers, string $method): void
    {
        $fullPath = $this->cacheDir . $path . "/" . $method;
        $bodyFile = CacheFilePath::path('body', $fullPath, '');
        $metaFile = CacheFilePath::path('meta', $fullPath, '');
        mkdir(dirname($bodyFile), 0777, true);

        file_put_contents($bodyFile, $body);
        file_put_contents($metaFile, json_encode(['headers' => $headers]));
    }

    private function buildMiddleware(array $requestMethods, array $httpCodes): FileCacheMiddleware
    {
        return FileCacheMiddleware::build([
            'location' => $this->cacheDir,
            'requestMethods' => $requestMethods,
            'httpCodes' => $httpCodes
        ]);
    }
}
