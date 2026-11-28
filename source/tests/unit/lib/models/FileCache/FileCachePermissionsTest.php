<?php

namespace Tent\Tests\Models\FileCache;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\FileCache;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;
use Tent\Tests\Support\Utils\FileSystemUtils;

class FileCachePermissionsTest extends TestCase
{
    private string $testDir;
    private string $cacheDir;
    private int $umask;

    protected function setUp(): void
    {
        $this->umask = umask();
        $this->testDir = sys_get_temp_dir() . '/filecache_permissions_test_' . uniqid();
        $this->cacheDir = $this->testDir . '/cache';
        mkdir($this->testDir, 0755, true);
        umask(0000);
    }

    protected function tearDown(): void
    {
        umask($this->umask);
        FileSystemUtils::removeDirRecursive($this->testDir);
    }

    public function testStoreCreatesDirectoriesNotWritableByOthers()
    {
        $this->buildCache()->store($this->buildResponse());

        $directories = ['', '/users', '/users/list', '/users/list/GET'];
        foreach ($directories as $directory) {
            $this->assertMode(0755, $this->cacheDir . $directory);
        }
    }

    public function testStoreCreatesFilesNotWritableByOthers()
    {
        $cache = $this->buildCache();

        $cache->store($this->buildResponse());

        $this->assertMode(0644, $cache->metaFilePath());
        $this->assertMode(0644, str_replace('.meta.json', '.body.dat', $cache->metaFilePath()));
    }

    public function testStoreLetsTheUmaskRestrictDirectoriesButNotFiles()
    {
        umask(0077);
        $cache = $this->buildCache();

        $cache->store($this->buildResponse());

        $this->assertMode(0700, $this->cacheDir . '/users/list/GET');
        $this->assertMode(0644, $cache->metaFilePath());
    }

    public function testStoreLeavesOnlyTheEntryFiles()
    {
        $cache = $this->buildCache();

        $cache->store($this->buildResponse());
        $cache->store($this->buildResponse());

        $meta = basename($cache->metaFilePath());
        $expected = [str_replace('.meta.json', '.body.dat', $meta), $meta];
        $files = array_values(array_diff(scandir($this->cacheDir . '/users/list/GET'), ['.', '..']));

        $this->assertSame($expected, $files);
    }

    public function testStoreDoesNotWriteTheMetaWhenTheBodyCannotBeWritten()
    {
        $cache = $this->buildCache();
        $bodyPath = str_replace('.meta.json', '.body.dat', $cache->metaFilePath());
        // A directory in place of the body makes its rename fail.
        mkdir($bodyPath, 0755, true);

        $cache->store($this->buildResponse());

        $this->assertFileDoesNotExist($cache->metaFilePath());
        $this->assertFalse($cache->exists());
        $this->assertSame(
            [basename($bodyPath)],
            array_values(array_diff(scandir(dirname($bodyPath)), ['.', '..']))
        );
    }

    private function assertMode(int $expected, string $path): void
    {
        clearstatcache();

        $this->assertSame(
            sprintf('%o', $expected),
            sprintf('%o', fileperms($path) & 0777),
            'Unexpected mode of ' . $path
        );
    }

    private function buildCache(): FileCache
    {
        return new FileCache($this->buildRequest(), new FolderLocation($this->cacheDir));
    }

    private function buildResponse(): Response
    {
        return new Response([
            'body' => 'cached body',
            'httpCode' => 200,
            'headers' => ['Content-Type: text/plain'],
            'request' => $this->buildRequest()
        ]);
    }

    private function buildRequest(): ProcessingRequest
    {
        return new ProcessingRequest(['requestPath' => '/users/list', 'requestMethod' => 'GET']);
    }
}
