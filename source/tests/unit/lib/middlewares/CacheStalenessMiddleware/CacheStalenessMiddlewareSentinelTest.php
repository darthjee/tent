<?php

namespace Tent\Tests\Middlewares\CacheStalenessMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\FileCache;
use Tent\Http\HttpClientInterface;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Middlewares\CacheStalenessMiddleware;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;
use Tent\Tests\Support\Utils\FileSystemUtils;

class CacheStalenessMiddlewareSentinelTest extends TestCase
{
    private string $testDir;
    private string $outsideFile;
    private FolderLocation $location;
    private ProcessingRequest $request;
    private string $sentinel;
    private int $umask;

    /**
     * @var array State of the sentinel observed each time a refresh was scheduled.
     */
    private array $scheduled = [];

    protected function setUp(): void
    {
        $this->umask = umask();
        $this->testDir = sys_get_temp_dir() . '/cache_staleness_sentinel_test_' . uniqid();
        $this->outsideFile = $this->testDir . '/outside/file';
        mkdir($this->testDir . '/cache', 0755, true);
        mkdir($this->testDir . '/outside', 0755, true);
        $this->location = new FolderLocation($this->testDir . '/cache');
        Logger::setInstance(new NullLoggerInstance());
        $this->request = $this->buildStaleRequest();
        $this->sentinel = (new FileCache($this->request, $this->location))->metaFilePath() . '.refreshing';
        umask(0000);
    }

    protected function tearDown(): void
    {
        umask($this->umask);
        FileSystemUtils::removeDirRecursive($this->testDir);
        Logger::setInstance(new LoggerInstance());
    }

    public function testSentinelIsARegularFileNotWritableByOthers()
    {
        $this->buildMiddleware()->processRequest($this->request);

        $this->assertSame([['link' => false, 'file' => true, 'mode' => 0644]], $this->scheduled);
        $this->assertFileDoesNotExist($this->sentinel);
    }

    public function testSentinelReplacesADanglingLinkWithoutCreatingItsTarget()
    {
        symlink($this->outsideFile, $this->sentinel);

        $this->buildMiddleware()->processRequest($this->request);

        $this->assertSame([['link' => false, 'file' => true, 'mode' => 0644]], $this->scheduled);
        $this->assertFileDoesNotExist($this->outsideFile);
    }

    public function testLinkToAnExistingOutsideFileDebouncesWithoutTouchingIt()
    {
        file_put_contents($this->outsideFile, 'untouched');
        symlink($this->outsideFile, $this->sentinel);

        $this->buildMiddleware()->processRequest($this->request);

        $this->assertSame([], $this->scheduled);
        $this->assertStringEqualsFile($this->outsideFile, 'untouched');
    }

    /**
     * A link appearing after the debounce check is only reachable by calling the writer.
     */
    public function testWritingTheSentinelReplacesALinkWithoutTouchingItsTarget()
    {
        file_put_contents($this->outsideFile, 'untouched');
        symlink($this->outsideFile, $this->sentinel);

        $method = new \ReflectionMethod(CacheStalenessMiddleware::class, 'writeSentinel');
        $method->setAccessible(true);
        $method->invoke($this->buildMiddleware(), $this->sentinel);

        $this->assertStringEqualsFile($this->outsideFile, 'untouched');
        $this->assertSame(['link' => false, 'file' => true, 'mode' => 0644], $this->sentinelState());
    }

    private function sentinelState(): array
    {
        clearstatcache();

        return [
            'link' => is_link($this->sentinel),
            'file' => is_file($this->sentinel),
            'mode' => fileperms($this->sentinel) & 0777
        ];
    }

    private function buildMiddleware(): CacheStalenessMiddleware
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn(['body' => 'fresh body', 'httpCode' => 200, 'headers' => []]);

        return new CacheStalenessMiddleware(
            $this->location,
            300,
            'http://api:80',
            $httpClient,
            function (callable $task) {
                $this->scheduled[] = $this->sentinelState();
                $task();
            }
        );
    }

    private function buildStaleRequest(): ProcessingRequest
    {
        $request = new ProcessingRequest([
            'requestPath' => '/users',
            'requestMethod' => 'GET',
            'headers' => [],
            'query' => ''
        ]);
        $response = new Response([
            'body' => 'stale body',
            'httpCode' => 200,
            'headers' => [],
            'request' => $request
        ]);
        $cache = new FileCache($request, $this->location);
        $cache->store($response);

        $meta = json_decode(file_get_contents($cache->metaFilePath()), true);
        $meta['timestamp'] = time() - 1000;
        file_put_contents($cache->metaFilePath(), json_encode($meta));
        $request->setResponse($response);

        return $request;
    }
}
