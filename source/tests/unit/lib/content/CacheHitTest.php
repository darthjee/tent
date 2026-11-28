<?php

namespace Tent\Tests\Content;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\CacheHit;
use Tent\Content\ExcludedHeaderFilter;
use Tent\Content\FileCache;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;

class CacheHitTest extends TestCase
{
    public function testCacheReturnsTheServedEntry()
    {
        $cache = $this->buildCache();
        $hit = new CacheHit($cache, new ExcludedHeaderFilter([]));

        $this->assertSame($cache, $hit->cache());
    }

    public function testHeaderFilterReturnsTheGivenFilter()
    {
        $headerFilter = new ExcludedHeaderFilter(['Set-Cookie']);
        $hit = new CacheHit($this->buildCache(), $headerFilter);

        $this->assertSame($headerFilter, $hit->headerFilter());
    }

    private function buildCache(): FileCache
    {
        $request = new ProcessingRequest(['requestPath' => '/data.json', 'requestMethod' => 'GET']);

        return new FileCache($request, new FolderLocation(sys_get_temp_dir() . '/tent_cache_hit'));
    }
}
