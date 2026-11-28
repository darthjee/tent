<?php

namespace Tent\Tests\Models\ProcessingRequest;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\CacheHit;
use Tent\Content\ExcludedHeaderFilter;
use Tent\Content\FileCache;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;

class ProcessingRequestCacheHitTest extends TestCase
{
    public function testCacheHitIsNullByDefault()
    {
        $this->assertNull((new ProcessingRequest())->cacheHit());
    }

    public function testSetCacheHitReturnsTheGivenMarker()
    {
        $request = new ProcessingRequest(['requestPath' => '/data.json', 'requestMethod' => 'GET']);
        $hit = $this->buildHit($request);

        $this->assertSame($hit, $request->setCacheHit($hit));
    }

    public function testCacheHitReturnsTheSetMarker()
    {
        $request = new ProcessingRequest(['requestPath' => '/data.json', 'requestMethod' => 'GET']);
        $hit = $this->buildHit($request);
        $request->setCacheHit($hit);

        $this->assertSame($hit, $request->cacheHit());
    }

    public function testSetCacheHitDoesNotSetAResponse()
    {
        $request = new ProcessingRequest(['requestPath' => '/data.json', 'requestMethod' => 'GET']);
        $request->setCacheHit($this->buildHit($request));

        $this->assertFalse($request->hasResponse());
    }

    private function buildHit(ProcessingRequest $request): CacheHit
    {
        $cache = new FileCache($request, new FolderLocation(sys_get_temp_dir() . '/tent_cache_hit'));

        return new CacheHit($cache, new ExcludedHeaderFilter([]));
    }
}
