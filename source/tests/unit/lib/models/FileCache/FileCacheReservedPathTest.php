<?php

namespace Tent\Tests\Models\FileCache;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\FileCache;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;
use Tent\Tests\Support\Utils\FileSystemUtils;

class FileCacheReservedPathTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/filecache_reserved_path_test_' . uniqid();
        mkdir($this->cacheDir);
        Logger::setInstance(new NullLoggerInstance());
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->cacheDir);
        Logger::setInstance(new LoggerInstance());
    }

    /**
     * @dataProvider reservedPathProvider
     */
    public function testReservedEntryNeverExistsEvenWithFilesOnDisk(string $path)
    {
        $cache = $this->buildCache($path);
        [$bodyPath, $metaPath] = $this->plantEntry($cache, $path);

        $this->assertFileExists($bodyPath);
        $this->assertFileExists($metaPath);
        $this->assertFalse($cache->exists());
        $this->assertNull($cache->timestamp());
    }

    /**
     * @dataProvider reservedPathProvider
     */
    public function testStoreCreatesNothingForReservedEntry(string $path)
    {
        $cache = $this->buildCache($path);

        $cache->store($this->buildResponse($path));

        $this->assertSame([], array_diff(scandir($this->cacheDir), ['.', '..']));
        $this->assertFalse($cache->exists());
    }

    /**
     * @dataProvider reservedPathProvider
     */
    public function testRemoveKeepsFilesOfReservedEntry(string $path)
    {
        $cache = $this->buildCache($path);
        [$bodyPath, $metaPath] = $this->plantEntry($cache, $path);

        $cache->remove();

        $this->assertStringEqualsFile($bodyPath, 'planted body');
        $this->assertFileExists($metaPath);
    }

    public function reservedPathProvider(): array
    {
        return [
            'entry' => ['/.tent-trash/x'],
            'trash itself' => ['/.tent-trash'],
            'upper case' => ['/.TENT-TRASH/x'],
            'dot segment' => ['/./.tent-trash/x'],
            'double slash' => ['//.tent-trash/x'],
            'parent segment' => ['/users/../.tent-trash/x'],
        ];
    }

    public function testDeeperTrashNameIsCachedNormally()
    {
        $cache = $this->buildCache('/users/.tent-trash');

        $cache->store($this->buildResponse('/users/.tent-trash'));

        $this->assertDirectoryExists($this->cacheDir . '/users/.tent-trash/GET');
        $this->assertTrue($cache->exists());
        $this->assertSame('cached body', $cache->content());
        $this->assertNotNull($cache->timestamp());

        $cache->remove();

        $this->assertFalse($cache->exists());
    }

    private function buildCache(string $path): FileCache
    {
        return new FileCache($this->buildRequest($path), new FolderLocation($this->cacheDir));
    }

    private function plantEntry(FileCache $cache, string $path): array
    {
        // Lets paths going through `users/..` resolve on disk.
        mkdir($this->cacheDir . '/users');

        $dir = $this->cacheDir . '/' . trim($path, '/') . '/GET';
        mkdir($dir, 0777, true);

        $metaName = basename($cache->metaFilePath());
        $metaPath = $dir . '/' . $metaName;
        $bodyPath = $dir . '/' . str_replace('.meta.json', '.body.dat', $metaName);

        file_put_contents($bodyPath, 'planted body');
        file_put_contents($metaPath, json_encode(['headers' => [], 'httpCode' => 200, 'timestamp' => 1]));

        return [$bodyPath, $metaPath];
    }

    private function buildResponse(string $path): Response
    {
        return new Response([
            'body' => 'cached body',
            'httpCode' => 200,
            'headers' => ['Content-Type: text/plain'],
            'request' => $this->buildRequest($path)
        ]);
    }

    private function buildRequest(string $path): ProcessingRequest
    {
        return new ProcessingRequest(['requestPath' => $path, 'requestMethod' => 'GET']);
    }
}
