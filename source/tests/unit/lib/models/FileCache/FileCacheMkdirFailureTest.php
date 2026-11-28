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
use Tent\Tests\Support\Utils\PhpWarningCatcher;

/**
 * A regular file standing where a directory is needed makes `mkdir()` fail, even
 * when the specs run as root.
 */
class FileCacheMkdirFailureTest extends TestCase
{
    private string $cacheDir;

    private CapturingLoggerInstance $logger;

    private PhpWarningCatcher $catcher;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/filecache_mkdir_failure_test_' . uniqid();
        mkdir($this->cacheDir, 0777, true);

        $this->logger = new CapturingLoggerInstance();
        Logger::setInstance($this->logger);

        $this->catcher = new PhpWarningCatcher();
        $this->catcher->start();
    }

    protected function assertPostConditions(): void
    {
        $this->assertSame([], $this->catcher->warnings());
    }

    protected function tearDown(): void
    {
        $this->catcher->stop();
        FileSystemUtils::removeDirRecursive($this->cacheDir);
        Logger::setInstance(new LoggerInstance());
    }

    /**
     * @dataProvider blockingFileProvider
     */
    public function testStoreLogsTheReasonAsAWarning(string $blockingFile, string $reason)
    {
        $this->createBlockingFile($blockingFile);

        $this->buildCache()->store($this->buildResponse());

        $message = 'cache store skipped — path: /a/b, method: GET, reason: ' . $reason;
        $this->assertSame([['warn', $message]], $this->logger->lines);
    }

    /**
     * @dataProvider blockingFileProvider
     */
    public function testStoreWritesNothing(string $blockingFile)
    {
        $this->createBlockingFile($blockingFile);
        $before = $this->listTree();
        $cache = $this->buildCache();

        $cache->store($this->buildResponse());

        $this->assertSame($before, $this->listTree());
        $this->assertStringEqualsFile($this->cacheDir . '/' . $blockingFile, 'blocking');
        $this->assertFalse($cache->exists());
    }

    public function blockingFileProvider(): array
    {
        return [
            'file in place of an ancestor' => ['a', 'mkdir(): Not a directory'],
            'file in place of the entry directory' => ['a/b/GET', 'mkdir(): File exists'],
        ];
    }

    private function createBlockingFile(string $blockingFile): void
    {
        $path = $this->cacheDir . '/' . $blockingFile;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, 'blocking');
    }

    private function buildCache(): FileCache
    {
        $request = new ProcessingRequest(['requestPath' => '/a/b', 'requestMethod' => 'GET']);

        return new FileCache($request, new FolderLocation($this->cacheDir));
    }

    private function buildResponse(): Response
    {
        return new Response([
            'body' => 'cached body',
            'httpCode' => 200,
            'headers' => ['Content-Type: text/plain']
        ]);
    }

    private function listTree(): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cacheDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        $paths = array_keys(iterator_to_array($iterator));
        sort($paths);

        return $paths;
    }
}
