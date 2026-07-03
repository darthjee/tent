<?php

namespace Tent\Tests\Content\CacheDirResolver;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\CacheDirResolver;
use Tent\Models\FolderLocation;

class CacheDirResolverResolveExactTest extends TestCase
{
    private CacheDirResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new CacheDirResolver(new FolderLocation('/cache'));
    }

    public function testResolveExactForSingleSegmentPath()
    {
        $dir = $this->resolver->resolveExact('/games.json');

        $this->assertSame('/cache/games.json/GET', $dir);
    }

    public function testResolveExactForMultiSegmentPath()
    {
        $dir = $this->resolver->resolveExact('/games/space-invaders.json');

        $this->assertSame('/cache/games/space-invaders.json/GET', $dir);
    }

    public function testResolveExactTrimsTrailingSlashesFromBasePath()
    {
        $resolver = new CacheDirResolver(new FolderLocation('/cache/'));

        $dir = $resolver->resolveExact('/games.json');

        $this->assertSame('/cache/games.json/GET', $dir);
    }
}
