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

    /**
     * @dataProvider unsafePathProvider
     */
    public function testResolveExactReturnsNullForUnsafePath(string $path)
    {
        $this->assertNull($this->resolver->resolveExact($path));
    }

    public function unsafePathProvider(): array
    {
        return [
            'relative parent segment' => ['/a/../b'],
            'parent segment above root' => ['/../x'],
            'trailing current segment' => ['/a/.'],
            'NUL byte' => ["/a/b\0c"],
        ];
    }

    /**
     * @dataProvider safeLookAlikeProvider
     */
    public function testResolveExactStillResolvesSafeLookAlikes(string $name)
    {
        $this->assertSame('/cache/' . $name . '/GET', $this->resolver->resolveExact('/' . $name));
    }

    public function safeLookAlikeProvider(): array
    {
        return [
            'three dots' => ['...'],
            'name starting with two dots' => ['..foo'],
            'name containing two dots' => ['a..b'],
            'dot-prefixed name' => ['.git'],
            'encoded dots' => ['%2e%2e'],
        ];
    }
}
