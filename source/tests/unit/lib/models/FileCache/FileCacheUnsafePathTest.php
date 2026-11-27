<?php

namespace Tent\Tests\Models\FileCache;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\FileCache;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;
use Tent\Tests\Support\Log\CapturingLoggerInstance;
use Tent\Tests\Support\Utils\FileSystemUtils;

class FileCacheUnsafePathTest extends TestCase
{
    private string $parentDir;

    private string $cacheDir;

    private CapturingLoggerInstance $logger;

    protected function setUp(): void
    {
        $this->parentDir = sys_get_temp_dir() . '/filecache_unsafe_path_test_' . uniqid();
        $this->cacheDir = $this->parentDir . '/root/cache';
        mkdir($this->cacheDir, 0777, true);
        // Lets paths going through `a/..` resolve on disk.
        mkdir($this->cacheDir . '/a');

        $this->logger = new CapturingLoggerInstance();
        Logger::setInstance($this->logger);
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->parentDir);
        Logger::setInstance(new LoggerInstance());
    }

    /**
     * @dataProvider unsafeRequestProvider
     */
    public function testUnsafeEntryNeverExists(string $path, string $method)
    {
        $cache = $this->buildCache($path, $method);

        $this->assertFalse($cache->exists());
        $this->assertNull($cache->timestamp());
    }

    /**
     * @dataProvider unsafeRequestProvider
     */
    public function testStoreCreatesNothingInsideNorOutsideTheRoot(string $path, string $method)
    {
        $before = $this->listTree();
        $cache = $this->buildCache($path, $method);

        $cache->store($this->buildResponse($path, $method));

        $this->assertSame($before, $this->listTree());
        $this->assertFalse($cache->exists());
    }

    /**
     * @dataProvider unsafeRequestProvider
     */
    public function testPlantedEntryAtTheTargetIsNeverServed(string $path, string $method)
    {
        $cache = $this->buildCache($path, $method);
        [$bodyPath, $metaPath] = $this->plantEntry($cache, $path, $method);

        $this->assertFileExists($bodyPath);
        $this->assertFileExists($metaPath);
        $this->assertFalse($cache->exists());
        $this->assertNull($cache->timestamp());
        $this->assertSame('', $cache->content());
        $this->assertSame([], $cache->headers());
        $this->assertSame(200, $cache->httpCode());
    }

    /**
     * @dataProvider unsafeRequestProvider
     */
    public function testRemoveKeepsFilesPlantedAtTheTarget(string $path, string $method)
    {
        $cache = $this->buildCache($path, $method);
        [$bodyPath, $metaPath] = $this->plantEntry($cache, $path, $method);

        $cache->remove();

        $this->assertStringEqualsFile($bodyPath, 'planted body');
        $this->assertFileExists($metaPath);
    }

    /**
     * @dataProvider unsafeRequestProvider
     */
    public function testLogsAWarningForUnsafeEntry(string $path, string $method)
    {
        $this->buildCache($path, $method);

        $message = 'cache bypassed — path: ' . $path . ', method: ' . $method . ', reason: unsafe path';

        $this->assertSame([['warn', $message]], $this->logger->lines);
    }

    public function unsafeRequestProvider(): array
    {
        return [
            'parent segment above root' => ['/../x', 'GET'],
            'parent segments climbing out' => ['/a/../../x', 'GET'],
            'current segment' => ['/a/./b', 'GET'],
            'parent segment as method' => ['/', '..'],
        ];
    }

    public function testNulByteInThePathIsInert()
    {
        $before = $this->listTree();
        $cache = $this->buildCache("/a/b\0c", 'GET');

        $cache->store($this->buildResponse("/a/b\0c", 'GET'));
        $cache->remove();

        $this->assertFalse($cache->exists());
        $this->assertNull($cache->timestamp());
        $this->assertSame($before, $this->listTree());
    }

    /**
     * @dataProvider safePathProvider
     */
    public function testSafeLookAlikeIsStoredAndReadBack(string $path)
    {
        $cache = $this->buildCache($path, 'GET');

        $cache->store($this->buildResponse($path, 'GET'));

        $this->assertDirectoryExists($this->cacheDir . $path . '/GET');
        $this->assertTrue($cache->exists());
        $this->assertSame('cached body', $cache->content());
        $this->assertNotNull($cache->timestamp());
        $this->assertSame([], $this->logger->lines);
    }

    public function safePathProvider(): array
    {
        return [
            'three dots' => ['/...'],
            'name starting with two dots' => ['/..foo'],
            'name containing two dots' => ['/a..b'],
        ];
    }

    private function buildCache(string $path, string $method): FileCache
    {
        return new FileCache($this->buildRequest($path, $method), new FolderLocation($this->cacheDir));
    }

    private function plantEntry(FileCache $cache, string $path, string $method): array
    {
        $dir = $this->cacheDir . '/' . trim($path . '/' . $method, '/');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $metaName = basename($cache->metaFilePath());
        $metaPath = $dir . '/' . $metaName;
        $bodyPath = $dir . '/' . str_replace('.meta.json', '.body.dat', $metaName);

        file_put_contents($bodyPath, 'planted body');
        file_put_contents($metaPath, json_encode([
            'headers' => ['Content-Type: text/plain'],
            'httpCode' => 201,
            'timestamp' => 1
        ]));

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

    private function buildResponse(string $path, string $method): Response
    {
        return new Response([
            'body' => 'cached body',
            'httpCode' => 200,
            'headers' => ['Content-Type: text/plain'],
            'request' => $this->buildRequest($path, $method)
        ]);
    }

    private function buildRequest(string $path, string $method): ProcessingRequest
    {
        return new ProcessingRequest(['requestPath' => $path, 'requestMethod' => $method]);
    }
}
