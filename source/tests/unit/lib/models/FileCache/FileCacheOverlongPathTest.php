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
use Tent\Tests\Support\Cache\DummyRequestHasher;
use Tent\Tests\Support\Log\CapturingLoggerInstance;
use Tent\Tests\Support\Utils\FileSystemUtils;
use Tent\Tests\Support\Utils\PhpWarningCatcher;

/**
 * Boundaries assume the Linux limits: 255 bytes per name and a `PATH_MAX` of 4096,
 * out of which PHP accepts paths of 4094 bytes at most.
 */
class FileCacheOverlongPathTest extends TestCase
{
    // Length of the `.tmp-<16 hex>` suffix of the temporary files.
    private const TEMPORARY_SUFFIX_LENGTH = 21;

    private const LONGEST_PATH = 4094;

    private string $parentDir;

    private string $cacheDir;

    private CapturingLoggerInstance $logger;

    private PhpWarningCatcher $catcher;

    protected function setUp(): void
    {
        $this->parentDir = sys_get_temp_dir() . '/filecache_overlong_path_test_' . uniqid();
        $this->cacheDir = $this->parentDir . '/cache';
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
        FileSystemUtils::removeDirRecursive($this->parentDir);
        Logger::setInstance(new LoggerInstance());
    }

    /**
     * @dataProvider overlongCaseProvider
     */
    public function testOverlongEntryIsNeverAHit(string $case)
    {
        $cache = $this->buildOverlongCache($case);

        $this->assertFalse($cache->exists());
        $this->assertNull($cache->timestamp());
        $this->assertSame('', $cache->content());
    }

    /**
     * @dataProvider overlongCaseProvider
     */
    public function testStoreLeavesTheTreeUnchanged(string $case)
    {
        $before = $this->listTree();
        $cache = $this->buildOverlongCache($case);

        $cache->store($this->buildResponse());

        $this->assertSame($before, $this->listTree());
        $this->assertFalse($cache->exists());
    }

    /**
     * @dataProvider overlongCaseProvider
     */
    public function testRemoveDoesNothing(string $case)
    {
        $before = $this->listTree();
        $cache = $this->buildOverlongCache($case);

        $cache->remove();

        $this->assertSame($before, $this->listTree());
    }

    /**
     * @dataProvider overlongCaseProvider
     */
    public function testLogsASingleWarning(string $case)
    {
        $cache = $this->buildOverlongCache($case);

        $cache->exists();
        $cache->store($this->buildResponse());
        $cache->remove();

        $this->assertCount(1, $this->logger->lines);
        [$level, $message] = $this->logger->lines[0];
        $this->assertSame('warn', $level);
        $this->assertStringStartsWith('cache bypassed — path: ', $message);
        $this->assertStringEndsWith(', reason: path too long', $message);
    }

    public function overlongCaseProvider(): array
    {
        return [
            'segment of 256 bytes' => ['segment'],
            'segment of 256 bytes in 128 characters' => ['multibyte segment'],
            'method of 256 bytes' => ['method'],
            'derived path of 4095 bytes' => ['full path'],
            'derived path far beyond the limit' => ['huge path'],
            'path made long by duplicate slashes' => ['duplicate slashes'],
            'hash of 225 characters' => ['leaf name'],
        ];
    }

    /**
     * Only for the cases whose body and meta files the filesystem can still hold.
     *
     * @dataProvider plantableCaseProvider
     */
    public function testEntryPlantedAtTheTargetIsNeitherServedNorRemoved(string $case)
    {
        $cache = $this->buildOverlongCache($case);
        $metaPath = $cache->metaFilePath();
        $bodyPath = str_replace('.meta.json', '.body.dat', $metaPath);

        mkdir(dirname($metaPath), 0777, true);
        file_put_contents($bodyPath, 'planted body');
        file_put_contents($metaPath, json_encode(['httpCode' => 201, 'timestamp' => 1]));

        $this->assertStringEqualsFile($bodyPath, 'planted body');
        $this->assertFileExists($metaPath);
        $this->assertFalse($cache->exists());
        $this->assertNull($cache->timestamp());
        $this->assertSame('', $cache->content());
        $this->assertSame([], $cache->headers());
        $this->assertSame(200, $cache->httpCode());

        $cache->remove();

        $this->assertStringEqualsFile($bodyPath, 'planted body');
        $this->assertFileExists($metaPath);
    }

    public function plantableCaseProvider(): array
    {
        return [
            'derived path of 4095 bytes' => ['full path'],
            'hash of 225 characters' => ['leaf name'],
        ];
    }

    public function testSegmentOf255BytesIsStored()
    {
        $cache = $this->buildCache('/a/' . str_repeat('s', 255));

        $this->assertStoredAndReadBack($cache);
    }

    public function testDerivedPathOfTheLongestLengthIsStored()
    {
        $cache = $this->buildCache($this->pathWithLongestDerivedFile(self::LONGEST_PATH));

        $this->assertSame(
            self::LONGEST_PATH,
            strlen($cache->metaFilePath()) + self::TEMPORARY_SUFFIX_LENGTH
        );
        $this->assertStoredAndReadBack($cache);
    }

    public function testHashOf224CharactersIsStored()
    {
        $cache = $this->buildCache('/a', 'GET', str_repeat('h', 224));

        $this->assertSame(255, strlen(basename($cache->metaFilePath())) + self::TEMPORARY_SUFFIX_LENGTH);
        $this->assertStoredAndReadBack($cache);
    }

    public function testUnsafeAndOverlongPathIsOnlyReportedAsUnsafe()
    {
        $path = '/a/../' . str_repeat('s', 300);

        $this->buildCache($path);

        $message = 'cache bypassed — path: ' . $path . ', method: GET, reason: unsafe path';
        $this->assertSame([['warn', $message]], $this->logger->lines);
    }

    public function testLoggedPathIsTruncated()
    {
        $path = '/' . str_repeat('a', 199) . str_repeat('b', 2800);

        $this->buildCache($path);

        $message = 'cache bypassed — path: /' . str_repeat('a', 199) . '… (3000 bytes)' .
            ', method: GET, reason: path too long';
        $this->assertSame([['warn', $message]], $this->logger->lines);
        $this->assertStringNotContainsString($path, $this->logger->lines[0][1]);
    }

    public function testLoggedMethodIsTruncated()
    {
        $this->buildCache('/a', str_repeat('M', 3000));

        $message = 'cache bypassed — path: /a, method: ' . str_repeat('M', 200) . '… (3000 bytes)' .
            ', reason: path too long';
        $this->assertSame([['warn', $message]], $this->logger->lines);
    }

    private function assertStoredAndReadBack(FileCache $cache): void
    {
        $cache->store($this->buildResponse());

        $this->assertTrue($cache->exists());
        $this->assertSame('cached body', $cache->content());
        $this->assertNotNull($cache->timestamp());
        $this->assertSame([], $this->logger->lines);
    }

    private function buildOverlongCache(string $case): FileCache
    {
        switch ($case) {
            case 'segment':
                return $this->buildCache('/a/' . str_repeat('s', 256));
            case 'multibyte segment':
                return $this->buildCache('/' . str_repeat('é', 128));
            case 'method':
                return $this->buildCache('/a', str_repeat('M', 256));
            case 'full path':
                return $this->buildCache($this->pathWithLongestDerivedFile(self::LONGEST_PATH + 1));
            case 'huge path':
                return $this->buildCache(str_repeat('/' . str_repeat('s', 200), 30));
            case 'duplicate slashes':
                return $this->buildCache('/a' . str_repeat('/', 5000) . 'b');
            case 'leaf name':
                return $this->buildCache('/a', 'GET', str_repeat('h', 225));
            default:
                throw new \InvalidArgumentException("Unknown case: $case");
        }
    }

    /**
     * Builds a path of short enough segments, for which the temporary file of the
     * metadata, with the default hash and the GET method, has the given length.
     */
    private function pathWithLongestDerivedFile(int $length): string
    {
        $file = $this->cacheDir . '//GET/' . hash('sha256', '') . '.meta.json';
        $remaining = $length - strlen($file) - self::TEMPORARY_SUFFIX_LENGTH;
        $segments = [];

        while ($remaining > 201) {
            $segments[] = str_repeat('s', 200);
            $remaining -= 201;
        }
        $segments[] = str_repeat('s', $remaining);

        return '/' . implode('/', $segments);
    }

    private function buildCache(string $path, string $method = 'GET', ?string $hash = null): FileCache
    {
        $request = new ProcessingRequest(['requestPath' => $path, 'requestMethod' => $method]);
        $hasher = $hash === null ? null : new DummyRequestHasher($hash);

        return new FileCache($request, new FolderLocation($this->cacheDir), $hasher);
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
            new \RecursiveDirectoryIterator($this->parentDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        $paths = array_keys(iterator_to_array($iterator));
        sort($paths);

        return $paths;
    }
}
