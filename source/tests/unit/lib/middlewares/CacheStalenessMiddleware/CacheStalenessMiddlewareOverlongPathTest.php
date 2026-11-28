<?php

namespace Tent\Tests\Middlewares\CacheStalenessMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\FileCache;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Log\NullLoggerInstance;
use Tent\Middlewares\CacheStalenessMiddleware;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;
use Tent\Tests\Support\Utils\FileSystemUtils;
use Tent\Tests\Support\Utils\PhpWarningCatcher;

class CacheStalenessMiddlewareOverlongPathTest extends TestCase
{
    private string $cacheDir;

    private PhpWarningCatcher $catcher;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/cache_staleness_overlong_test_' . uniqid();
        mkdir($this->cacheDir, 0777, true);
        Logger::setInstance(new NullLoggerInstance());

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

    public function testRefreshSentinelSuffixFitsTheFileCacheHeadroom()
    {
        $this->assertLessThanOrEqual(
            FileCache::DERIVED_SUFFIX_HEADROOM,
            strlen(CacheStalenessMiddleware::REFRESH_SENTINEL_SUFFIX)
        );
    }

    public function testOverlongPathSchedulesNoRefreshAndWritesNothing()
    {
        $scheduled = 0;
        $middleware = new CacheStalenessMiddleware(
            new FolderLocation($this->cacheDir),
            0,
            'http://upstream',
            scheduler: function () use (&$scheduled) {
                $scheduled++;
            }
        );
        $request = new ProcessingRequest([
            'requestPath' => '/' . str_repeat('s', 300),
            'requestMethod' => 'GET'
        ]);
        $request->setResponse(new Response(['body' => 'cached body', 'httpCode' => 200, 'request' => $request]));

        $result = $middleware->processRequest($request);

        $this->assertSame($request, $result);
        $this->assertSame(0, $scheduled);
        $this->assertSame(['.', '..'], scandir($this->cacheDir));
    }
}
