<?php

namespace Tent\Tests\Middlewares\FileCacheMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';


use PHPUnit\Framework\TestCase;
use Tent\Middlewares\FileCacheMiddleware;
use Tent\Models\FolderLocation;
use Tent\Models\Response;
use Tent\Models\ProcessingRequest;
use Tent\Content\FileCache;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Utils\CacheFilePath;
use Tent\Tests\Support\Utils\FileSystemUtils;

class FileCacheMiddlewareProcessResponseTest extends TestCase
{
    private $cacheDir;
    private $location;
    private $headers;
    private $cache;
    private $request;
    private $path;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/filecache_middleware_test_' . uniqid();
        mkdir($this->cacheDir);
        $this->location = new FolderLocation($this->cacheDir);
        Logger::setInstance(new NullLoggerInstance());
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->cacheDir);
        Logger::setInstance(new LoggerInstance());
    }

    public function testProcessResponseStoresCache()
    {
        $response = $this->buildResponse(200);

        $middleware = $this->buildMiddleware();
        $middleware->processResponse($response);

        $this->cache = new FileCache($this->request, $this->location);
        $this->assertTrue($this->cache->exists());
        $this->assertEquals('cached body', $this->cache->content());
        foreach ($this->headers as $header) {
            $this->assertContains($header, $this->cache->headers());
        }
    }

    public function testProcessResponseWrongCode()
    {
        $response = $this->buildResponse(403);

        $middleware = $this->buildMiddleware();
        $middleware->processResponse($response);

        $this->cache = new FileCache($this->request, $this->location);
        $this->assertFalse($this->cache->exists());
    }

    public function testProcessResponseWithConfiguredHttpCode()
    {
        $response = $this->buildResponse(403);

        $middleware = $this->buildMiddleware([403]);
        $middleware->processResponse($response);

        $this->cache = new FileCache($this->request, $this->location);
        $this->assertTrue($this->cache->exists());
    }

    public function testProcessResponseWithWildCardCodes()
    {
        $response = $this->buildResponse(403);

        $middleware = $this->buildMiddleware(['4XX']);
        $middleware->processResponse($response);

        $this->cache = new FileCache($this->request, $this->location);
        $this->assertTrue($this->cache->exists());
    }

    public function testProcessResponseDoesNotOverwriteExistingCache()
    {
        $response = $this->buildResponse(200);

        $hash = hash('sha256', '');
        $bodyFile = CacheFilePath::path('body', $this->cacheDir . '/file.txt', $hash);
        $metaFile = CacheFilePath::path('meta', $this->cacheDir . '/file.txt', $hash);
        mkdir(dirname($bodyFile), 0777, true);
        file_put_contents($bodyFile, 'original body');
        file_put_contents($metaFile, json_encode(['headers' => ["Header1: original", "Header2: value"]]));

        $middleware = $this->buildMiddleware();
        $middleware->processResponse($response);

        $this->assertEquals('original body', file_get_contents($bodyFile));
        $meta = json_decode(file_get_contents($metaFile), true);
        $this->assertEquals(["Header1: original", "Header2: value"], $meta['headers']);
    }

    public function testProcessResponseWithSkipCacheHeaderConfiguredAndMissingKeepsCurrentBehavior()
    {
        $response = $this->buildResponse(200);

        $middleware = FileCacheMiddleware::build([
            'location' => $this->cacheDir,
            'skip_cache_header' => 'X-Skip-Cache',
            'matchers' => [
                [
                    'class' => \Tent\Matchers\StatusCodeMatcher::class,
                    'httpCodes' => [200],
                ]
            ],
        ]);
        $middleware->processResponse($response);

        $this->cache = new FileCache($this->request, $this->location);
        $this->assertTrue($this->cache->exists());
    }

    public function testProcessResponseSkipsCacheWriteWhenSkipCacheHeaderIsPresent()
    {
        $response = $this->buildResponse(200, [], ['X-Skip-Cache: 1']);

        $middleware = FileCacheMiddleware::build([
            'location' => $this->cacheDir,
            'skip_cache_header' => 'X-Skip-Cache',
            'matchers' => [
                [
                    'class' => \Tent\Matchers\StatusCodeMatcher::class,
                    'httpCodes' => [200],
                ]
            ],
        ]);
        $middleware->processResponse($response);

        $this->cache = new FileCache($this->request, $this->location);
        $this->assertFalse($this->cache->exists());
    }

    public function testProcessResponseSkipsCacheWriteCaseInsensitively()
    {
        $response = $this->buildResponse(200, [], ['x-skip-cache: 1']);

        $middleware = FileCacheMiddleware::build([
            'location' => $this->cacheDir,
            'skip_cache_header' => 'X-SKIP-CACHE',
            'matchers' => [
                [
                    'class' => \Tent\Matchers\StatusCodeMatcher::class,
                    'httpCodes' => [200],
                ]
            ],
        ]);
        $middleware->processResponse($response);

        $this->cache = new FileCache($this->request, $this->location);
        $this->assertFalse($this->cache->exists());
    }

    public function testProcessResponseSkipsCacheWriteWhenSkipCacheHeaderIsOnlyInRequest()
    {
        $response = $this->buildResponse(200, ['X-Skip-Cache' => '1']);

        $middleware = FileCacheMiddleware::build([
            'location' => $this->cacheDir,
            'skip_cache_header' => 'X-Skip-Cache',
            'matchers' => [
                [
                    'class' => \Tent\Matchers\StatusCodeMatcher::class,
                    'httpCodes' => [200],
                ]
            ],
        ]);
        $middleware->processResponse($response);

        $this->cache = new FileCache($this->request, $this->location);
        $this->assertFalse($this->cache->exists());
    }

    public function testProcessResponseWithRequireCacheHeaderNotConfiguredKeepsCurrentBehavior()
    {
        $response = $this->buildResponse(200);

        $middleware = $this->buildMiddleware();
        $middleware->processResponse($response);

        $this->cache = new FileCache($this->request, $this->location);
        $this->assertTrue($this->cache->exists());
    }

    public function testProcessResponseStoresCacheWhenRequireCacheHeaderIsPresentInResponse()
    {
        $response = $this->buildResponse(200, [], ['X-Cache-Allow: 1']);

        $middleware = FileCacheMiddleware::build([
            'location' => $this->cacheDir,
            'require_cache_header' => 'X-Cache-Allow',
            'matchers' => [
                [
                    'class' => \Tent\Matchers\StatusCodeMatcher::class,
                    'httpCodes' => [200],
                ]
            ],
        ]);
        $middleware->processResponse($response);

        $this->cache = new FileCache($this->request, $this->location);
        $this->assertTrue($this->cache->exists());
    }

    public function testProcessResponseSkipsCacheWriteWhenRequireCacheHeaderIsMissingFromResponse()
    {
        $response = $this->buildResponse(200);

        $middleware = FileCacheMiddleware::build([
            'location' => $this->cacheDir,
            'require_cache_header' => 'X-Cache-Allow',
            'matchers' => [
                [
                    'class' => \Tent\Matchers\StatusCodeMatcher::class,
                    'httpCodes' => [200],
                ]
            ],
        ]);
        $middleware->processResponse($response);

        $this->cache = new FileCache($this->request, $this->location);
        $this->assertFalse($this->cache->exists());
    }

    public function testProcessResponseStoresCacheWhenRequireCacheHeaderMatchesCaseInsensitively()
    {
        $response = $this->buildResponse(200, [], ['x-cache-allow: 1']);

        $middleware = FileCacheMiddleware::build([
            'location' => $this->cacheDir,
            'require_cache_header' => 'X-CACHE-ALLOW',
            'matchers' => [
                [
                    'class' => \Tent\Matchers\StatusCodeMatcher::class,
                    'httpCodes' => [200],
                ]
            ],
        ]);
        $middleware->processResponse($response);

        $this->cache = new FileCache($this->request, $this->location);
        $this->assertTrue($this->cache->exists());
    }

    public function testProcessResponseSkipsCacheWriteWhenRequireCacheHeaderIsOnlyInRequest()
    {
        $response = $this->buildResponse(200, ['X-Cache-Allow' => '1']);

        $middleware = FileCacheMiddleware::build([
            'location' => $this->cacheDir,
            'require_cache_header' => 'X-Cache-Allow',
            'matchers' => [
                [
                    'class' => \Tent\Matchers\StatusCodeMatcher::class,
                    'httpCodes' => [200],
                ]
            ],
        ]);
        $middleware->processResponse($response);

        $this->cache = new FileCache($this->request, $this->location);
        $this->assertFalse($this->cache->exists());
    }

    public function testProcessResponseSkipsCacheWriteWhenBothHeadersConfiguredAndBothPresentInResponse()
    {
        $response = $this->buildResponse(200, [], ['X-Skip-Cache: 1', 'X-Cache-Allow: 1']);

        $middleware = FileCacheMiddleware::build([
            'location' => $this->cacheDir,
            'skip_cache_header' => 'X-Skip-Cache',
            'require_cache_header' => 'X-Cache-Allow',
            'matchers' => [
                [
                    'class' => \Tent\Matchers\StatusCodeMatcher::class,
                    'httpCodes' => [200],
                ]
            ],
        ]);
        $middleware->processResponse($response);

        $this->cache = new FileCache($this->request, $this->location);
        $this->assertFalse($this->cache->exists());
    }

    public function testProcessResponseStoresCacheWhenBothConfiguredAndOnlyRequireCacheHeaderPresent()
    {
        $response = $this->buildResponse(200, [], ['X-Cache-Allow: 1']);

        $middleware = FileCacheMiddleware::build([
            'location' => $this->cacheDir,
            'skip_cache_header' => 'X-Skip-Cache',
            'require_cache_header' => 'X-Cache-Allow',
            'matchers' => [
                [
                    'class' => \Tent\Matchers\StatusCodeMatcher::class,
                    'httpCodes' => [200],
                ]
            ],
        ]);
        $middleware->processResponse($response);

        $this->cache = new FileCache($this->request, $this->location);
        $this->assertTrue($this->cache->exists());
    }

    public function testProcessResponseStripsDefaultDangerousHeadersWithZeroConfig()
    {
        $response = $this->buildResponse(200, [], ['Set-Cookie: session=abc']);

        $middleware = $this->buildMiddleware();
        $middleware->processResponse($response);

        $this->cache = new FileCache($this->request, $this->location);
        $this->assertTrue($this->cache->exists());
        $this->assertNotContains('Set-Cookie: session=abc', $this->cache->headers());
    }

    public function testProcessResponseStripsHeadersConfiguredViaExcludedHeaders()
    {
        $response = $this->buildResponse(200, [], ['X-Secret: value']);

        $middleware = FileCacheMiddleware::build([
            'location' => $this->cacheDir,
            'excluded_headers' => ['X-Secret'],
            'matchers' => [
                [
                    'class' => \Tent\Matchers\StatusCodeMatcher::class,
                    'httpCodes' => [200],
                ]
            ],
        ]);
        $middleware->processResponse($response);

        $this->cache = new FileCache($this->request, $this->location);
        $this->assertNotContains('X-Secret: value', $this->cache->headers());
    }

    public function testProcessResponseStripsHeadersConfiguredViaAdditionalExcludedHeaders()
    {
        $response = $this->buildResponse(200, [], ['Set-Cookie: session=abc', 'X-Secret: value']);

        $middleware = FileCacheMiddleware::build([
            'location' => $this->cacheDir,
            'additional_excluded_headers' => ['X-Secret'],
            'matchers' => [
                [
                    'class' => \Tent\Matchers\StatusCodeMatcher::class,
                    'httpCodes' => [200],
                ]
            ],
        ]);
        $middleware->processResponse($response);

        $this->cache = new FileCache($this->request, $this->location);
        $this->assertNotContains('Set-Cookie: session=abc', $this->cache->headers());
        $this->assertNotContains('X-Secret: value', $this->cache->headers());
    }

    public function testProcessResponseKeepsOnlyAllowedHeadersWhenModeIsAllow()
    {
        $response = $this->buildResponse(200, [], ['Set-Cookie: session=abc']);

        $middleware = FileCacheMiddleware::build([
            'location' => $this->cacheDir,
            'mode' => 'allow',
            'allowed_headers' => ['Content-Type'],
            'matchers' => [
                [
                    'class' => \Tent\Matchers\StatusCodeMatcher::class,
                    'httpCodes' => [200],
                ]
            ],
        ]);
        $middleware->processResponse($response);

        $this->cache = new FileCache($this->request, $this->location);
        $this->assertContains('Content-Type: text/plain', $this->cache->headers());
        $this->assertNotContains('Content-Length: 11', $this->cache->headers());
        $this->assertNotContains('Set-Cookie: session=abc', $this->cache->headers());
    }

    public function testProcessResponseReturnsUnfilteredResponseToTheCaller()
    {
        $response = $this->buildResponse(200, [], ['Set-Cookie: session=abc']);

        $middleware = $this->buildMiddleware();
        $result = $middleware->processResponse($response);

        $this->assertContains('Set-Cookie: session=abc', $result->headers());
        $this->assertSame($response, $result);
    }

    private function buildResponse(int $httpCode, array $requestHeaders = [], array $responseHeaders = [])
    {
        $this->path = '/file.txt';
        $this->headers = array_merge(['Content-Type: text/plain', 'Content-Length: 11'], $responseHeaders);
        $this->request = $this->buildRequest($this->path, 'GET', $requestHeaders);

        return new Response([
            'body' => 'cached body', 'httpCode' => $httpCode, 'headers' => $this->headers,
            'request' => $this->request
        ]);
    }

    private function buildRequest(string $path, string $method, array $headers = []): ProcessingRequest
    {
        return new ProcessingRequest([
            'headers' => $headers,
            'requestPath' => $path,
            'requestMethod' => $method
        ]);
    }

    private function buildMiddleware(array $httpCodes = [200]): FileCacheMiddleware
    {
        return FileCacheMiddleware::build([
            'location' => $this->cacheDir,
            'matchers' => [
                [
                    'class' => \Tent\Matchers\StatusCodeMatcher::class,
                    'httpCodes' => $httpCodes,
                ]
            ],
        ]);
    }
}
