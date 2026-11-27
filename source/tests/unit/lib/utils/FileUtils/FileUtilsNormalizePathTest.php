<?php

namespace Tent\Tests\Utils\FileUtils;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Utils\FileUtils;

class FileUtilsNormalizePathTest extends TestCase
{
    /**
     * @dataProvider normalizationProvider
     */
    public function testNormalizePath(string $path, string $expected)
    {
        $this->assertSame($expected, FileUtils::normalizePath($path));
    }

    public function normalizationProvider(): array
    {
        return [
            'already normalized absolute' => ['/var/cache/users', '/var/cache/users'],
            'already normalized relative' => ['cache/users', 'cache/users'],
            'repeated slashes' => ['/var//cache///users', '/var/cache/users'],
            'repeated leading slashes' => ['//var/cache', '/var/cache'],
            'dot segments' => ['/var/./cache/./users', '/var/cache/users'],
            'trailing dot segment' => ['/var/cache/.', '/var/cache'],
            'parent segment' => ['/var/cache/users/../products', '/var/cache/products'],
            'consecutive parent segments' => ['/var/cache/users/1/../../products', '/var/cache/products'],
            'leading dot slash' => ['./cache/users', 'cache/users'],
            'mixed' => ['./cache/./.tent-trash//x/GET', 'cache/.tent-trash/x/GET'],
            'trailing slash' => ['/var/cache/', '/var/cache'],
            'root' => ['/', '/'],
            'parent above the absolute root' => ['/../cache', '/../cache'],
            'parent above the relative base' => ['./cache/../../x', '../x'],
            'repeated parents above the base' => ['../../cache', '../../cache'],
            'dotted names are kept' => ['/cache/.../..x/.hidden', '/cache/.../..x/.hidden'],
            'zero segment is kept' => ['/cache/0/GET', '/cache/0/GET'],
        ];
    }
}
