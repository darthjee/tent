<?php

namespace Tent\Tests\Middlewares\RenameHeaderMiddleware;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Middlewares\RenameHeaderMiddleware;
use Tent\Models\ProcessingRequest;

class RenameHeaderMiddlewareTest extends TestCase
{
    public function testRenamesHeader()
    {
        $request = new ProcessingRequest([
            'headers' => [
                'Host'       => 'original_host',
                'User-Agent' => 'PHPUnit',
            ]
        ]);

        $middleware = new RenameHeaderMiddleware('Host', 'X-Original-Host');
        $result = $middleware->processRequest($request);

        $this->assertSame($request, $result);
        $this->assertEquals(
            [
                'X-Original-Host' => 'original_host',
                'User-Agent'      => 'PHPUnit',
            ],
            $result->headers()
        );
    }

    public function testDoesNothingWhenFromHeaderIsMissing()
    {
        $requestHeaders = [
            'User-Agent' => 'PHPUnit',
        ];

        $request = new ProcessingRequest([
            'headers' => $requestHeaders
        ]);

        $middleware = new RenameHeaderMiddleware('Host', 'X-Original-Host');
        $result = $middleware->processRequest($request);

        $this->assertSame($request, $result);
        $this->assertEquals($requestHeaders, $result->headers());
    }

    public function testCaseSensitiveHeaderLookup()
    {
        $request = new ProcessingRequest([
            'headers' => [
                'host' => 'lowercase_host',
            ]
        ]);

        $middleware = new RenameHeaderMiddleware('Host', 'X-Original-Host');
        $result = $middleware->processRequest($request);

        // 'Host' (capital H) is absent; 'host' (lowercase) must remain untouched
        $this->assertEquals(['host' => 'lowercase_host'], $result->headers());
    }
}
