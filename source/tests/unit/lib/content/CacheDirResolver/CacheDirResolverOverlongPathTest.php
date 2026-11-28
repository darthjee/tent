<?php

namespace Tent\Tests\Content\CacheDirResolver;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\CacheDirResolver;
use Tent\Models\FolderLocation;
use Tent\Tests\Support\Utils\PhpWarningCatcher;

class CacheDirResolverOverlongPathTest extends TestCase
{
    private string $cacheDir;

    private CacheDirResolver $resolver;

    private PhpWarningCatcher $catcher;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/cache_dir_resolver_overlong_test_' . uniqid();
        $this->resolver = new CacheDirResolver(new FolderLocation($this->cacheDir));

        $this->catcher = new PhpWarningCatcher();
        $this->catcher->start();
    }

    protected function assertPostConditions(): void
    {
        $this->assertSame([], $this->catcher->warnings());
    }

    protected function tearDown(): void
    {
        $this->catcher->stop();
    }

    /**
     * @dataProvider overlongPathProvider
     */
    public function testResolveReturnsAPathOrNull(string $path)
    {
        foreach (['collection', 'entity'] as $target) {
            $this->assertThat(
                $this->resolver->resolve($target, $path),
                $this->logicalOr($this->isNull(), $this->stringStartsWith($this->cacheDir . '/'))
            );
        }
    }

    /**
     * @dataProvider overlongPathProvider
     */
    public function testResolveExactReturnsThePathAsIs(string $path)
    {
        $this->assertSame($this->cacheDir . $path . '/GET', $this->resolver->resolveExact($path));
    }

    public function overlongPathProvider(): array
    {
        return [
            'segment of 300 bytes' => ['/' . str_repeat('s', 300)],
            'nested segment of 300 bytes' => ['/a/' . str_repeat('s', 300) . '/1'],
            'path longer than 4096 bytes' => [str_repeat('/' . str_repeat('s', 200), 25)],
        ];
    }
}
