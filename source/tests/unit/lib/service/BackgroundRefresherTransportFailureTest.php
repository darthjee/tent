<?php

namespace Tent\Tests\Service;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\EvictionPolicy;
use Tent\Content\FileCache;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;
use Tent\Service\BackgroundRefresher;
use Tent\Tests\Support\Http\StubHttpClient;
use Tent\Tests\Support\Log\CapturingLoggerInstance;
use Tent\Tests\Support\Utils\FileSystemUtils;

class BackgroundRefresherTransportFailureTest extends TestCase
{
    private string $cacheDir;
    private ProcessingRequest $request;
    private FileCache $cache;
    private CapturingLoggerInstance $logger;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/background_refresher_failure_test_' . uniqid();
        mkdir($this->cacheDir);

        $this->logger = new CapturingLoggerInstance();
        Logger::setInstance($this->logger);

        $this->request = new ProcessingRequest([
            'requestPath' => '/users',
            'requestMethod' => 'GET',
            'query' => 'token=secret',
            'headers' => ['Authorization' => 'Bearer credential'],
        ]);
        $this->cache = new FileCache($this->request, new FolderLocation($this->cacheDir));
        $this->storeStaleEntry();
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->cacheDir);
        Logger::setInstance(new LoggerInstance());
    }

    public static function transportFailures(): array
    {
        return [
            'partial 200 with empty body' => [CURLE_PARTIAL_FILE, 200, ''],
            'partial 200 with some body' => [CURLE_PARTIAL_FILE, 200, 'fres'],
            'timeout mid-body' => [CURLE_OPERATION_TIMEDOUT, 200, 'fres'],
            'connection failure' => [CURLE_COULDNT_CONNECT, 0, ''],
            'partial 503' => [CURLE_PARTIAL_FILE, 503, 'err'],
        ];
    }

    /**
     * @dataProvider transportFailures
     */
    public function testKeepsTheEntryUntouched(int $errno, int $httpCode, string $body)
    {
        $content = $this->cache->content();
        $headers = $this->cache->headers();
        $timestamp = $this->cache->timestamp();

        $this->refresher($errno, $httpCode, $body)->run();

        $this->assertSame($content, $this->cache->content());
        $this->assertSame($headers, $this->cache->headers());
        $this->assertSame(200, $this->cache->httpCode());
        $this->assertSame($timestamp, $this->cache->timestamp());
    }

    /**
     * @dataProvider transportFailures
     */
    public function testDoesNotEvictTheEntry(int $errno, int $httpCode, string $body)
    {
        $policy = new EvictionPolicy(['2xx', '5xx']);

        $this->refresher($errno, $httpCode, $body, $policy)->run();

        $this->assertTrue($this->cache->exists());
        $this->assertSame('stale body', $this->cache->content());
    }

    public function testLogsAWarningWithoutSensitiveRequestData()
    {
        $this->refresher(CURLE_PARTIAL_FILE, 200, 'fres')->run();

        $warnings = array_values(array_filter($this->logger->lines, fn($line) => $line[0] === 'warn'));

        $this->assertCount(1, $warnings);
        $this->assertSame(
            '[refresh] - background cache refresh failed — uri: /users, ' .
            'reason: upstream transport failure (cURL errno: 18), keeping stale cache',
            $warnings[0][1]
        );

        foreach ($this->logger->lines as [, $message]) {
            $this->assertStringNotContainsString('secret', $message);
            $this->assertStringNotContainsString('credential', $message);
        }
    }

    public function testReplacesTheEntryWhenErrnoIsZero()
    {
        $this->refresher(0, 200, 'fresh body')->run();

        $this->assertSame('fresh body', $this->cache->content());
    }

    private function refresher(
        int $errno,
        int $httpCode,
        string $body,
        ?EvictionPolicy $policy = null
    ): BackgroundRefresher {
        $httpClient = new StubHttpClient([
            'body' => $body,
            'httpCode' => $httpCode,
            'headers' => [],
            'errno' => $errno,
            'error' => 'failure for http://api:80/users?token=secret',
        ]);

        return new BackgroundRefresher($this->request, $this->cache, 'http://api:80', $httpClient, null, $policy);
    }

    private function storeStaleEntry(): void
    {
        $this->cache->store(new Response([
            'body' => 'stale body',
            'httpCode' => 200,
            'headers' => ['Content-Type: text/plain'],
            'request' => $this->request,
        ]));

        $meta = json_decode(file_get_contents($this->cache->metaFilePath()), true);
        $meta['timestamp'] = time() - 1000;
        file_put_contents($this->cache->metaFilePath(), json_encode($meta));
    }
}
