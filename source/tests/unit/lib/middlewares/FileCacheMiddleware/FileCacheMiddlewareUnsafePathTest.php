<?php

namespace Tent\Tests\Middlewares\FileCacheMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\FileCache;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Middlewares\FileCacheMiddleware;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;
use Tent\Tests\Support\Utils\FileSystemUtils;

class FileCacheMiddlewareUnsafePathTest extends TestCase
{
    private string $parentDir;
    private string $cacheDir;
    private FileCacheMiddleware $middleware;

    protected function setUp(): void
    {
        $this->parentDir = sys_get_temp_dir() . '/filecache_middleware_unsafe_test_' . uniqid();
        $this->cacheDir = $this->parentDir . '/root/cache';
        mkdir($this->cacheDir, 0777, true);
        // Lets paths going through `a/..` resolve on disk.
        mkdir($this->cacheDir . '/a');
        Logger::setInstance(new NullLoggerInstance());
        $this->middleware = new FileCacheMiddleware(new FolderLocation($this->cacheDir), evictOn: ['4xx']);
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->parentDir);
        Logger::setInstance(new LoggerInstance());
    }

    /**
     * @dataProvider unsafePathProvider
     */
    public function testProcessRequestNeverServesAnEntryPlantedAtTheTarget(string $path)
    {
        $this->plantEntry($path);
        $request = $this->buildRequest($path);

        $result = $this->middleware->processRequest($request);

        $this->assertSame($request, $result);
        $this->assertFalse($result->hasResponse());
    }

    /**
     * @dataProvider unsafePathProvider
     */
    public function testProcessResponseStoresNothingInsideNorOutsideTheRoot(string $path)
    {
        $before = $this->listTree();
        $response = $this->buildResponse($path, 200);

        $result = $this->middleware->processResponse($response);

        $this->assertSame($response, $result);
        $this->assertSame(200, $result->httpCode());
        $this->assertSame('upstream body', $result->body());
        $this->assertSame($before, $this->listTree());
    }

    /**
     * @dataProvider unsafePathProvider
     */
    public function testProcessResponseDoesNotEvictFilesPlantedAtTheTarget(string $path)
    {
        [$bodyPath, $metaPath] = $this->plantEntry($path);

        $this->middleware->processResponse($this->buildResponse($path, 404));

        $this->assertStringEqualsFile($bodyPath, 'planted body');
        $this->assertFileExists($metaPath);
    }

    public function unsafePathProvider(): array
    {
        return [
            'parent segment above root' => ['/../x'],
            'parent segments climbing out' => ['/a/../../x'],
            'parent segment staying inside the root' => ['/a/../b'],
        ];
    }

    private function plantEntry(string $path): array
    {
        $cache = new FileCache($this->buildRequest($path), new FolderLocation($this->cacheDir));
        $metaPath = $cache->metaFilePath();
        $bodyPath = str_replace('.meta.json', '.body.dat', $metaPath);

        mkdir(dirname($metaPath), 0777, true);
        file_put_contents($bodyPath, 'planted body');
        file_put_contents($metaPath, json_encode(['headers' => [], 'httpCode' => 200, 'timestamp' => 1]));

        return [$bodyPath, $metaPath];
    }

    private function listTree(): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->parentDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        $paths = array_keys(iterator_to_array($iterator));
        sort($paths);

        return $paths;
    }

    private function buildResponse(string $path, int $httpCode): Response
    {
        return new Response([
            'body' => 'upstream body',
            'httpCode' => $httpCode,
            'headers' => ['Content-Type: text/plain'],
            'request' => $this->buildRequest($path)
        ]);
    }

    private function buildRequest(string $path): ProcessingRequest
    {
        return new ProcessingRequest(['requestPath' => $path, 'requestMethod' => 'GET']);
    }
}
