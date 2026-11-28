<?php

namespace Tent\Tests\Middlewares\CacheStalenessMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\CacheHit;
use Tent\Content\ExcludedHeaderFilter;
use Tent\Content\FileCache;
use Tent\Http\HttpClientInterface;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Middlewares\CacheStalenessMiddleware;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;
use Tent\Tests\Support\Log\CapturingLoggerInstance;
use Tent\Tests\Support\Utils\FileSystemUtils;

class CacheStalenessMiddlewareSentinelTest extends TestCase
{
    private const TTL = 300;
    private const OVERFLOWING_TIMESTAMP = '99999999999999999999999999';

    private string $testDir;
    private string $outsideFile;
    private string $fifo;
    private FolderLocation $location;
    private ProcessingRequest $request;
    private string $sentinel;
    private int $umask;

    /**
     * @var array State of the sentinel observed each time a refresh was scheduled.
     */
    private array $scheduled = [];

    /**
     * @var array Content of the sentinel observed each time a refresh was scheduled.
     */
    private array $scheduledContents = [];

    /**
     * @var integer Number of upstream requests performed by refreshes.
     */
    private int $refreshes = 0;

    /**
     * @var resource|null Handle keeping both ends of a FIFO open.
     */
    private $fifoHandle = null;

    protected function setUp(): void
    {
        $this->umask = umask();
        $this->testDir = sys_get_temp_dir() . '/cache_staleness_sentinel_test_' . uniqid();
        $this->outsideFile = $this->testDir . '/outside/file';
        $this->fifo = $this->testDir . '/outside/fifo';
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

        if (is_resource($this->fifoHandle)) {
            fclose($this->fifoHandle);
        }

        // removeDirRecursive() only removes files, links and directories.
        if (file_exists($this->fifo)) {
            unlink($this->fifo);
        }

        FileSystemUtils::removeDirRecursive($this->testDir);
        Logger::setInstance(new LoggerInstance());
    }

    public function testSentinelIsARegularFileNotWritableByOthers()
    {
        $this->buildMiddleware()->processResponse($this->request->response());

        $this->assertSame([['link' => false, 'file' => true, 'mode' => 0644]], $this->scheduled);
        $this->assertFileDoesNotExist($this->sentinel);
    }

    public function testSentinelReplacesADanglingLinkWithoutCreatingItsTarget()
    {
        symlink($this->outsideFile, $this->sentinel);

        $this->buildMiddleware()->processResponse($this->request->response());

        $this->assertSame([['link' => false, 'file' => true, 'mode' => 0644]], $this->scheduled);
        $this->assertFileDoesNotExist($this->outsideFile);
    }

    public function testLinkToAnExistingOutsideFileDebouncesWithoutTouchingIt()
    {
        file_put_contents($this->outsideFile, 'untouched');
        symlink($this->outsideFile, $this->sentinel);

        $this->buildMiddleware()->processResponse($this->request->response());

        // 'untouched' is not a timestamp: the sentinel is dated by the modification time of
        // the link target, which was just written, hence it is fresh.
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

    public function abandonedSentinelProvider(): array
    {
        // Content (an integer is a timestamp, as an offset from now) and mtime offset.
        return [
            'old timestamp' => [-3600, -3600],
            'old timestamp, recent mtime' => [-3600, 0],
            'empty content, old mtime' => ['', -3600],
            'non-numeric content, old mtime' => ['garbage', -3600],
            'suffixed token, old mtime' => ['1000:abc', -3600],
            'timestamp over a TTL ahead, old mtime' => [86400, -3600],
            'overflowing timestamp, old mtime' => [self::OVERFLOWING_TIMESTAMP, -3600],
        ];
    }

    /**
     * @dataProvider abandonedSentinelProvider
     */
    public function testAbandonedSentinelIsTakenOver(int|string $content, int $mtimeOffset)
    {
        $this->leaveSentinel($content, $mtimeOffset);
        $before = time();

        $this->buildMiddleware(self::TTL)->processResponse($this->request->response());

        $this->assertTakenOverSince($before);
    }

    public function freshSentinelProvider(): array
    {
        // Content (an integer is a timestamp, as an offset from now) and mtime offset.
        return [
            'recent timestamp' => [-100, 0],
            'recent timestamp, old mtime' => [-100, -3600],
            'timestamp within a TTL ahead, old mtime' => [30, -3600],
            'empty content, recent mtime' => ['', 0],
            'non-numeric content, recent mtime' => ['garbage', 0],
            'suffixed token, recent mtime' => ['1000:abc', 0],
            'numeric but not made of digits, recent mtime' => ['1e3', 0],
            'overflowing timestamp, recent mtime' => [self::OVERFLOWING_TIMESTAMP, 0],
        ];
    }

    /**
     * @dataProvider freshSentinelProvider
     */
    public function testFreshSentinelDebouncesWithoutBeingTouched(int|string $content, int $mtimeOffset)
    {
        $written = $this->leaveSentinel($content, $mtimeOffset);

        $this->buildMiddleware(self::TTL)->processResponse($this->request->response());

        $this->assertSame([], $this->scheduled);
        $this->assertStringEqualsFile($this->sentinel, $written);
    }

    public function testOnlyTheStartOfAnOversizedSentinelIsRead()
    {
        // Read as a whole, the content would be a current timestamp, and would debounce.
        $this->leaveSentinel(str_repeat(' ', 32) . time(), -3600);
        $before = time();

        $this->buildMiddleware(self::TTL)->processResponse($this->request->response());

        $this->assertTakenOverSince($before);
    }

    public function disabledTtlProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
        ];
    }

    /**
     * @dataProvider disabledTtlProvider
     */
    public function testDisabledTtlNeverTakesASentinelOver(int $ttl)
    {
        $written = $this->leaveSentinel(-86400, -86400);

        $this->buildMiddleware($ttl)->processResponse($this->request->response());

        $this->assertSame([], $this->scheduled);
        $this->assertStringEqualsFile($this->sentinel, $written);
    }

    public function testAbandonedLinkIsReplacedWithoutTouchingItsTarget()
    {
        file_put_contents($this->outsideFile, '1000');
        symlink($this->outsideFile, $this->sentinel);
        $before = time();

        $this->buildMiddleware(self::TTL)->processResponse($this->request->response());

        $this->assertTakenOverSince($before);
        $this->assertSame([['link' => false, 'file' => true, 'mode' => 0644]], $this->scheduled);
        $this->assertStringEqualsFile($this->outsideFile, '1000');
    }

    public function testAbandonedDirectorySentinelStillSchedulesTheRefresh()
    {
        mkdir($this->sentinel);
        touch($this->sentinel, time() - 3600);

        $this->buildMiddleware(self::TTL)->processResponse($this->request->response());

        $this->assertCount(1, $this->scheduled);
        $this->assertSame(1, $this->refreshes);
        $this->assertDirectoryExists($this->sentinel);
    }

    public function testFifoSentinelIsNeverRead()
    {
        if (!function_exists('posix_mkfifo')) {
            $this->markTestSkipped('posix_mkfifo() is not available');
        }

        $this->assertTrue(posix_mkfifo($this->fifo, 0644));
        // Both ends stay open, so reading neither blocks on open nor loses the content: it
        // would find an ancient timestamp, and take the sentinel over. The content fills the
        // 32 bytes read limit, as a shorter one would leave such a read waiting for more.
        $this->fifoHandle = fopen($this->fifo, 'r+');
        fwrite($this->fifoHandle, str_pad('1000', 32));
        touch($this->fifo, time());
        symlink($this->fifo, $this->sentinel);

        $this->buildMiddleware(self::TTL)->processResponse($this->request->response());

        $this->assertSame([], $this->scheduled);
    }

    public function freshnessProvider(): array
    {
        // TTL, age and whether the sentinel is fresh.
        return [
            'unknown age' => [self::TTL, null, true],
            'zero TTL' => [0, 86400, true],
            'negative TTL' => [-1, 86400, true],
            'negative TTL, zero age' => [-1, 0, true],
            'age equal to the TTL' => [self::TTL, self::TTL, true],
            'age over the TTL' => [self::TTL, self::TTL + 1, false],
            'negative age' => [self::TTL, -30, true],
        ];
    }

    /**
     * @dataProvider freshnessProvider
     */
    public function testIsFresh(int $ttl, ?int $age, bool $expected)
    {
        $this->assertSame($expected, $this->invokePrivate($this->buildMiddleware($ttl), 'isFresh', $age));
    }

    public function testSentinelWhichCannotBeDatedHasNoAge()
    {
        $this->assertFileDoesNotExist($this->sentinel);

        $this->assertNull(
            $this->invokePrivate($this->buildMiddleware(), 'sentinelAge', $this->sentinel, time())
        );
    }

    public function testTakeoverIsLoggedOnceAsAWarning()
    {
        $this->leaveSentinel(-3600, -3600);
        $logger = new CapturingLoggerInstance();
        Logger::setInstance($logger);

        $this->buildMiddleware(self::TTL)->processResponse($this->request->response());

        $warnings = $this->messagesAt($logger, 'warn');
        $this->assertCount(1, $warnings);
        $this->assertMatchesRegularExpression(
            '#^\[stale\] - abandoned refresh sentinel taken over — uri: /users, ' .
            'age: 36\d\d, sentinelTtlSeconds: 300$#',
            $warnings[0]
        );
    }

    public function testDebounceIsLoggedAsDebugWithoutAWarning()
    {
        $this->leaveSentinel(-100, 0);
        $logger = new CapturingLoggerInstance();
        Logger::setInstance($logger);

        $this->buildMiddleware(self::TTL)->processResponse($this->request->response());

        $this->assertContains(
            '[stale] - refresh already in progress, skipping — uri: /users',
            $this->messagesAt($logger, 'debug')
        );
        $this->assertSame([], $this->messagesAt($logger, 'warn'));
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

    /**
     * Leaves a sentinel behind, as a previous refresh would.
     *
     * @param int|string $content     A timestamp, as an offset from now, or a literal content.
     * @param int        $mtimeOffset The modification time, as an offset from now.
     * @return string The written content.
     */
    private function leaveSentinel(int|string $content, int $mtimeOffset): string
    {
        $content = is_int($content) ? (string) (time() + $content) : $content;
        file_put_contents($this->sentinel, $content);
        touch($this->sentinel, time() + $mtimeOffset);

        return $content;
    }

    /**
     * Asserts that one refresh was scheduled, with the sentinel rewritten at that time, and
     * that it ran, removing the sentinel.
     */
    private function assertTakenOverSince(int $since): void
    {
        $this->assertCount(1, $this->scheduled);
        $this->assertTrue(ctype_digit($this->scheduledContents[0]));
        $this->assertGreaterThanOrEqual($since, (int) $this->scheduledContents[0]);
        $this->assertLessThanOrEqual(time(), (int) $this->scheduledContents[0]);
        $this->assertSame(1, $this->refreshes);
        $this->assertFileDoesNotExist($this->sentinel);
    }

    private function messagesAt(CapturingLoggerInstance $logger, string $level): array
    {
        $lines = array_filter($logger->lines, fn (array $line) => $line[0] === $level);

        return array_column($lines, 1);
    }

    private function invokePrivate(CacheStalenessMiddleware $middleware, string $name, mixed ...$arguments): mixed
    {
        $method = new \ReflectionMethod(CacheStalenessMiddleware::class, $name);
        $method->setAccessible(true);

        return $method->invoke($middleware, ...$arguments);
    }

    private function buildMiddleware(?int $sentinelTtlSeconds = null): CacheStalenessMiddleware
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturnCallback(function () {
                $this->refreshes++;

                return ['body' => 'fresh body', 'httpCode' => 200, 'headers' => []];
            });

        return new CacheStalenessMiddleware(
            $this->location,
            300,
            'http://api:80',
            $httpClient,
            function (callable $task) {
                $this->scheduled[] = $this->sentinelState();
                $this->scheduledContents[] = is_file($this->sentinel) ? file_get_contents($this->sentinel) : null;
                $task();
            },
            sentinelTtlSeconds: $sentinelTtlSeconds
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
        $request->setCacheHit(new CacheHit($cache, new ExcludedHeaderFilter([])));

        return $request;
    }
}
