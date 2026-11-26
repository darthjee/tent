<?php

namespace Tent\Tests\Models\ProcessingRequest;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Models\ProcessingRequest;

class ProcessingRequestCacheSkipTest extends TestCase
{
    public function testCacheSkippedIsFalseByDefault()
    {
        $this->assertFalse((new ProcessingRequest())->cacheSkipped());
    }

    public function testCacheSkippedIsFalseWithUnrelatedParams()
    {
        $request = new ProcessingRequest([
            'requestPath' => '/a',
            'headers' => ['Accept' => 'x'],
        ]);

        $this->assertFalse($request->cacheSkipped());
    }

    public function testSkipCacheSetsTheFlag()
    {
        $request = new ProcessingRequest();
        $request->skipCache();

        $this->assertTrue($request->cacheSkipped());
    }

    public function testSkipCacheIsIdempotent()
    {
        $request = new ProcessingRequest();
        $request->skipCache();
        $request->skipCache();

        $this->assertTrue($request->cacheSkipped());
    }

    public function testSkipCacheDoesNotChangeHeaders()
    {
        $request = new ProcessingRequest(['headers' => ['Accept' => 'x']]);
        $request->skipCache();

        $this->assertEquals(['Accept' => 'x'], $request->headers());
    }
}
