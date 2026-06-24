<?php

namespace Tent\Tests\Content\CacheDirResolver;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\CacheDirResolver;
use Tent\Models\FolderLocation;

class CacheDirResolverResolveTest extends TestCase
{
    private CacheDirResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new CacheDirResolver(new FolderLocation('/cache'));
    }

    public function testResolveCollectionForCollectionPath()
    {
        $dir = $this->resolver->resolve('collection', '/users');

        $this->assertSame('/cache/users/GET', $dir);
    }

    public function testResolveCollectionForEntityPath()
    {
        $dir = $this->resolver->resolve('collection', '/users/1');

        $this->assertSame('/cache/users/GET', $dir);
    }

    public function testResolveCollectionForNestedEntityPath()
    {
        $dir = $this->resolver->resolve('collection', '/users/1/posts/2');

        $this->assertSame('/cache/users/1/posts/GET', $dir);
    }

    public function testResolveEntityForEntityPath()
    {
        $dir = $this->resolver->resolve('entity', '/users/1');

        $this->assertSame('/cache/users/1/GET', $dir);
    }

    public function testResolveEntityReturnsNullForSingleSegmentPath()
    {
        $dir = $this->resolver->resolve('entity', '/users');

        $this->assertNull($dir);
    }

    public function testResolveReturnsNullForUnknownTarget()
    {
        $dir = $this->resolver->resolve('unknown', '/users/1');

        $this->assertNull($dir);
    }

    public function testResolveTrimsTrailingSlashesFromBasePath()
    {
        $resolver = new CacheDirResolver(new FolderLocation('/cache/'));

        $dir = $resolver->resolve('collection', '/users');

        $this->assertSame('/cache/users/GET', $dir);
    }
}
