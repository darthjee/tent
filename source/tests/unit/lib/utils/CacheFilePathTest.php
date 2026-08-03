<?php

namespace Tent\Tests\Utils;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Utils\CacheFilePath;
use InvalidArgumentException;

class CacheFilePathTest extends TestCase
{
    private const HASH = 'a8b771920b8319e47251d1360f5e880bc18e8d329b0f0d003ea3c7e615558947';

    public function testPathReturnsBodyCacheFile()
    {
        $expected = '/tmp/' . self::HASH . '.body.dat';
        $this->assertEquals($expected, CacheFilePath::path('body', '/tmp', self::HASH));
    }

    public function testPathReturnsMetaCacheFile()
    {
        $expected = '/tmp/' . self::HASH . '.meta.json';
        $this->assertEquals($expected, CacheFilePath::path('meta', '/tmp', self::HASH));
    }

    public function testPathUsesTheGivenHashVerbatimWithoutRehashing()
    {
        $expected = '/tmp/not-a-real-hash.body.dat';
        $this->assertEquals($expected, CacheFilePath::path('body', '/tmp', 'not-a-real-hash'));
    }

    public function testPathThrowsOnInvalidType()
    {
        $this->expectException(InvalidArgumentException::class);
        CacheFilePath::path('invalid', '/tmp', self::HASH);
    }
}
