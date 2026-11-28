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

class FileCacheSymlinkTest extends TestCase
{
    private string $testDir;
    private string $cacheDir;
    private string $outsideDir;
    private CapturingLoggerInstance $logger;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/filecache_symlink_test_' . uniqid();
        $this->cacheDir = $this->testDir . '/cache';
        $this->outsideDir = $this->testDir . '/outside';
        mkdir($this->cacheDir, 0755, true);
        mkdir($this->outsideDir, 0755, true);

        $this->logger = new CapturingLoggerInstance();
        Logger::setInstance($this->logger);
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->testDir);
        Logger::setInstance(new LoggerInstance());
    }

    /**
     * @dataProvider linkedDirectoryProvider
     */
    public function testStoreWritesNothingThroughALinkPointingOutside(string $link)
    {
        $linkPath = $this->cacheDir . '/' . $link;
        if (!is_dir(dirname($linkPath))) {
            mkdir(dirname($linkPath), 0755, true);
        }
        symlink($this->outsideDir, $linkPath);
        $before = $this->listTree();
        $cache = $this->buildCache($this->cacheDir);

        $cache->store($this->buildResponse());

        $this->assertSame($before, $this->listTree());
        $this->assertFalse($cache->exists());
        $this->assertSkipLogged();
    }

    public static function linkedDirectoryProvider(): array
    {
        return [
            'first segment of the path' => ['users'],
            'intermediate segment of the path' => ['users/list'],
            'entry directory' => ['users/list/GET'],
        ];
    }

    public function testStoreWritesNothingIntoExistingDirectoriesReachedThroughALink()
    {
        mkdir($this->outsideDir . '/list/GET', 0755, true);
        symlink($this->outsideDir, $this->cacheDir . '/users');
        $before = $this->listTree();

        $this->buildCache($this->cacheDir)->store($this->buildResponse());

        $this->assertSame($before, $this->listTree());
        $this->assertSkipLogged();
    }

    /**
     * @dataProvider linkedDirectoryProvider
     */
    public function testStoreCreatesNothingThroughALinkToAMissingOutsidePath(string $link)
    {
        $linkPath = $this->cacheDir . '/' . $link;
        if (!is_dir(dirname($linkPath))) {
            mkdir(dirname($linkPath), 0755, true);
        }
        symlink($this->outsideDir . '/missing', $linkPath);
        $before = $this->listTree();
        $cache = $this->buildCache($this->cacheDir);

        $cache->store($this->buildResponse());

        $this->assertSame($before, $this->listTree());
        $this->assertDirectoryDoesNotExist($this->outsideDir . '/missing');
        $this->assertFalse($cache->exists());
        $this->assertSkipLogged();
    }

    public function testStoreDoesNotUseALookAlikeSiblingOfTheCacheLocation()
    {
        mkdir($this->testDir . '/cache-other');
        symlink($this->testDir . '/cache-other', $this->cacheDir . '/users');
        $before = $this->listTree();

        $this->buildCache($this->cacheDir)->store($this->buildResponse());

        $this->assertSame($before, $this->listTree());
        $this->assertSkipLogged();
    }

    public function testStoreReplacesLinksStandingInPlaceOfTheCacheFiles()
    {
        $cache = $this->buildCache($this->cacheDir);
        [$bodyPath, $metaPath] = $this->entryPaths($cache);
        $outsideBody = $this->outsideDir . '/body';
        $outsideMeta = $this->outsideDir . '/meta';
        file_put_contents($outsideBody, 'outside body');
        file_put_contents($outsideMeta, 'outside meta');
        mkdir(dirname($bodyPath), 0755, true);
        symlink($outsideBody, $bodyPath);
        symlink($outsideMeta, $metaPath);

        $cache->store($this->buildResponse());

        clearstatcache();
        $this->assertStringEqualsFile($outsideBody, 'outside body');
        $this->assertStringEqualsFile($outsideMeta, 'outside meta');
        $this->assertFalse(is_link($bodyPath));
        $this->assertFalse(is_link($metaPath));
        $this->assertTrue($cache->exists());
        $this->assertSame('cached body', $cache->content());
        $this->assertSame(201, $cache->httpCode());
        $this->assertSame([], $this->logger->lines);
    }

    public function testStoreDoesNotCreateTheTargetsOfDanglingFileLinks()
    {
        $cache = $this->buildCache($this->cacheDir);
        [$bodyPath, $metaPath] = $this->entryPaths($cache);
        mkdir(dirname($bodyPath), 0755, true);
        symlink($this->outsideDir . '/body', $bodyPath);
        symlink($this->outsideDir . '/meta', $metaPath);

        $cache->store($this->buildResponse());

        $this->assertFileDoesNotExist($this->outsideDir . '/body');
        $this->assertFileDoesNotExist($this->outsideDir . '/meta');
        $this->assertTrue($cache->exists());
        $this->assertSame('cached body', $cache->content());
    }

    public function testStoreAndReadWorkWhenTheCacheLocationIsALink()
    {
        symlink($this->cacheDir, $this->testDir . '/cache-link');
        $cache = $this->buildCache($this->testDir . '/cache-link');

        $cache->store($this->buildResponse());

        $this->assertTrue($cache->exists());
        $this->assertSame('cached body', $cache->content());
        $this->assertSame(201, $cache->httpCode());
        $this->assertDirectoryExists($this->cacheDir . '/users/list/GET');
        $this->assertSame([], $this->logger->lines);
    }

    public function testStoreThroughALinkPointingInsideTheCacheLocation()
    {
        mkdir($this->cacheDir . '/real');
        symlink($this->cacheDir . '/real', $this->cacheDir . '/users');
        $cache = $this->buildCache($this->cacheDir);

        $cache->store($this->buildResponse());

        $this->assertTrue($cache->exists());
        $this->assertSame('cached body', $cache->content());
        $this->assertDirectoryExists($this->cacheDir . '/real/list/GET');
        $this->assertSame([], $this->logger->lines);
    }

    /**
     * @dataProvider missingLocationProvider
     */
    public function testStoreCreatesAMissingCacheLocation(string $location)
    {
        $cache = $this->buildCache($this->testDir . $location);

        $cache->store($this->buildResponse());

        $this->assertTrue($cache->exists());
        $this->assertSame([], $this->logger->lines);
    }

    public static function missingLocationProvider(): array
    {
        return [
            'missing location' => ['/new-cache'],
            'missing location with a trailing slash' => ['/new-cache/'],
            'missing location and parent' => ['/new/nested/cache'],
        ];
    }

    public function testStoreSkipsWhenTheCacheLocationIsADanglingLink()
    {
        symlink($this->testDir . '/missing', $this->testDir . '/cache-link');
        $before = $this->listTree();

        $this->buildCache($this->testDir . '/cache-link')->store($this->buildResponse());

        $this->assertSame($before, $this->listTree());
        $this->assertSkipLogged();
    }

    private function assertSkipLogged(): void
    {
        $message = 'cache store skipped — path: /users/list, method: GET, ' .
            'reason: resolves outside cache location';

        $this->assertSame([['warn', $message]], $this->logger->lines);
    }

    private function entryPaths(FileCache $cache): array
    {
        $metaPath = $cache->metaFilePath();

        return [str_replace('.meta.json', '.body.dat', $metaPath), $metaPath];
    }

    private function listTree(): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->testDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        $paths = array_keys(iterator_to_array($iterator));
        sort($paths);

        return $paths;
    }

    private function buildCache(string $location): FileCache
    {
        return new FileCache($this->buildRequest(), new FolderLocation($location));
    }

    private function buildResponse(): Response
    {
        return new Response([
            'body' => 'cached body',
            'httpCode' => 201,
            'headers' => ['Content-Type: text/plain'],
            'request' => $this->buildRequest()
        ]);
    }

    private function buildRequest(): ProcessingRequest
    {
        return new ProcessingRequest(['requestPath' => '/users/list', 'requestMethod' => 'GET']);
    }
}
