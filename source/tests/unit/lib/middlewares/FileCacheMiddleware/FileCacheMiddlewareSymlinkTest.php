<?php

namespace Tent\Tests\Middlewares\FileCacheMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;
use Tent\Middlewares\FileCacheMiddleware;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;
use Tent\Tests\Support\Log\CapturingLoggerInstance;
use Tent\Tests\Support\Utils\FileSystemUtils;

class FileCacheMiddlewareSymlinkTest extends TestCase
{
    private string $testDir;
    private string $outsideDir;
    private CapturingLoggerInstance $logger;
    private FileCacheMiddleware $middleware;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/filecache_middleware_symlink_test_' . uniqid();
        $this->outsideDir = $this->testDir . '/outside';
        mkdir($this->testDir . '/cache', 0755, true);
        mkdir($this->outsideDir, 0755, true);
        symlink($this->outsideDir, $this->testDir . '/cache/users');

        $this->logger = new CapturingLoggerInstance();
        Logger::setInstance($this->logger);
        $this->middleware = new FileCacheMiddleware(new FolderLocation($this->testDir . '/cache'));
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->testDir);
        Logger::setInstance(new LoggerInstance());
    }

    public function testRequestIsAnsweredNormallyAndNothingIsWrittenOutside()
    {
        $request = $this->buildRequest();
        $response = new Response([
            'body' => 'upstream body',
            'httpCode' => 200,
            'headers' => ['Content-Type: text/plain'],
            'request' => $request
        ]);

        $this->assertFalse($this->middleware->processRequest($request)->hasResponse());
        $result = $this->middleware->processResponse($response);

        $this->assertSame($response, $result);
        $this->assertSame(200, $result->httpCode());
        $this->assertSame('upstream body', $result->body());
        $this->assertSame(['Content-Type: text/plain'], $result->headers());
        $this->assertSame(['.', '..'], scandir($this->outsideDir));
        $this->assertContains(
            [
                'warn',
                'cache store skipped — path: /users/list, method: GET, reason: resolves outside cache location'
            ],
            $this->logger->lines
        );
    }

    private function buildRequest(): ProcessingRequest
    {
        return new ProcessingRequest(['requestPath' => '/users/list', 'requestMethod' => 'GET']);
    }
}
