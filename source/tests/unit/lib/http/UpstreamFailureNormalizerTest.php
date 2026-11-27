<?php

namespace Tent\Tests\Http;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Http\HttpClientInterface;
use Tent\Http\UpstreamFailureNormalizer;
use Tent\Log\Logger;
use Tent\Log\LoggerInstance;

class CapturingLoggerInstance extends LoggerInstance
{
    public array $lines = [];

    public function log(string $message, string $level): void
    {
        $this->lines[] = [$level, $message];
    }
}

class StubHttpClient implements HttpClientInterface
{
    private array $result;

    public function __construct(array $result)
    {
        $this->result = $result;
    }

    public function request(
        string $method,
        string $url,
        array $headers,
        ?string $body = null,
        array $uploadedFiles = [],
        array $postFields = []
    ): array {
        return $this->result;
    }
}

class UpstreamFailureNormalizerTest extends TestCase
{
    private CapturingLoggerInstance $logger;

    protected function setUp(): void
    {
        $this->logger = new CapturingLoggerInstance();
        Logger::setInstance($this->logger);
    }

    private function call(array $result, string $url = 'http://api/x'): array
    {
        $normalizer = new UpstreamFailureNormalizer(new StubHttpClient($result));

        return $normalizer->request('GET', $url, []);
    }

    public function testSuccessIsKeptWithoutErrorKeys()
    {
        $result = $this->call([
            'body' => 'ok', 'httpCode' => 200, 'headers' => ['A: b'], 'errno' => 0, 'error' => ''
        ]);

        $this->assertSame(['body' => 'ok', 'httpCode' => 200, 'headers' => ['A: b']], $result);
    }

    public function testTimeoutBecomes504()
    {
        $result = $this->call([
            'body' => '', 'httpCode' => 0, 'headers' => [], 'errno' => CURLE_OPERATION_TIMEDOUT, 'error' => 't'
        ]);

        $this->assertSame(['body' => '', 'httpCode' => 504, 'headers' => []], $result);
    }

    public function testCouldntConnectBecomes502()
    {
        $result = $this->call([
            'body' => '', 'httpCode' => 0, 'headers' => [], 'errno' => CURLE_COULDNT_CONNECT, 'error' => 'c'
        ]);

        $this->assertSame(502, $result['httpCode']);
    }

    public function testCouldntResolveHostBecomes502()
    {
        $result = $this->call([
            'body' => '', 'httpCode' => 0, 'headers' => [], 'errno' => CURLE_COULDNT_RESOLVE_HOST, 'error' => 'r'
        ]);

        $this->assertSame(502, $result['httpCode']);
    }

    public function testZeroErrnoAndZeroHttpCodeBecomes502()
    {
        $result = $this->call(['body' => '', 'httpCode' => 0, 'headers' => [], 'errno' => 0, 'error' => '']);

        $this->assertSame(502, $result['httpCode']);
    }

    public function testErrorTextAndHeadersDoNotLeak()
    {
        $result = $this->call([
            'body' => 'secret',
            'httpCode' => 0,
            'headers' => ['X-Up: 1'],
            'errno' => CURLE_COULDNT_CONNECT,
            'error' => 'Failed to connect to api port 80'
        ]);

        $this->assertSame('', $result['body']);
        $this->assertSame([], $result['headers']);
        $this->assertSame(['body', 'httpCode', 'headers'], array_keys($result));
    }

    public function testResultWithoutErrnoKeysIsUnchanged()
    {
        $input = ['body' => 'ok', 'httpCode' => 200, 'headers' => []];

        $this->assertSame($input, $this->call($input));
    }

    public function testResultWithoutErrnoAndZeroHttpCodeBecomes502()
    {
        $result = $this->call(['body' => '', 'httpCode' => 0, 'headers' => []]);

        $this->assertSame(502, $result['httpCode']);
    }

    public function testLogsWithoutQueryString()
    {
        $this->call([
            'body' => '', 'httpCode' => 0, 'headers' => [],
            'errno' => CURLE_COULDNT_CONNECT, 'error' => 'Failed to connect'
        ], 'http://api:80/health.json?token=secret');

        $this->assertCount(1, $this->logger->lines);
        [$level, $line] = $this->logger->lines[0];
        $this->assertSame('warn', $level);
        $this->assertStringContainsString('errno: 7', $line);
        $this->assertStringContainsString('Failed to connect', $line);
        $this->assertStringContainsString('http://api:80/health.json', $line);
        $this->assertStringNotContainsString('token', $line);
    }

    public function testRealUpstreamErrorsPassThrough()
    {
        foreach ([502, 504] as $code) {
            $input = ['body' => 'up', 'httpCode' => $code, 'headers' => ['X: y'], 'errno' => 0, 'error' => ''];
            $result = $this->call($input);

            $this->assertSame(['body' => 'up', 'httpCode' => $code, 'headers' => ['X: y']], $result);
        }
        $this->assertSame([], $this->logger->lines);
    }
}
