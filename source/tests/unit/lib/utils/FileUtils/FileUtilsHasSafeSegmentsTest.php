<?php

namespace Tent\Tests\Utils\FileUtils;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Utils\FileUtils;

class FileUtilsHasSafeSegmentsTest extends TestCase
{
    /**
     * @dataProvider unsafePathProvider
     */
    public function testRejectsPathsWithDotSegmentsOrNulBytes(string $path)
    {
        $this->assertFalse(FileUtils::hasSafeSegments($path));
    }

    /**
     * @dataProvider safePathProvider
     */
    public function testAcceptsPathsWithoutDotSegmentsNorNulBytes(string $path)
    {
        $this->assertTrue(FileUtils::hasSafeSegments($path));
    }

    public function unsafePathProvider(): array
    {
        return [
            'relative parent segment' => ['/a/../b'],
            'parent segment above root' => ['/../x'],
            'trailing parent segment' => ['/a/..'],
            'trailing current segment' => ['/a/.'],
            'leading current segment' => ['./a'],
            'parent segment in a relative path' => ['a/../b'],
            'lone parent segment' => ['..'],
            'lone current segment' => ['.'],
            'NUL byte' => ["/a/b\0c"],
        ];
    }

    public function safePathProvider(): array
    {
        return [
            'plain path' => ['/a/b'],
            'three dots' => ['/...'],
            'name starting with two dots' => ['/..foo'],
            'name containing two dots' => ['/a..b'],
            'dot-prefixed name' => ['/.git'],
            'encoded dots' => ['/%2e%2e'],
            'backslash' => ['/a\\b'],
            'empty segment' => ['/a//b'],
            'empty path' => [''],
            'root' => ['/'],
        ];
    }
}
