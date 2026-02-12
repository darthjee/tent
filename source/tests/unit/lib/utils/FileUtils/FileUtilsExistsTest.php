<?php

namespace Tent\Tests\Utils\FileUtils;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Utils\FileUtils;

class FileUtilsExistsTest extends TestCase
{
    public function testExistsReturnsTrueForExistingFile()
    {
        $file = tempnam(sys_get_temp_dir(), 'test_file_');
        $this->assertTrue(FileUtils::exists($file));
        unlink($file);
    }

    public function testExistsReturnsFalseForNonExistingFile()
    {
        $file = sys_get_temp_dir() . '/non_existing_' . uniqid();
        $this->assertFalse(FileUtils::exists($file));
    }

    public function testExistsReturnsFalseForDirectory()
    {
        $dir = sys_get_temp_dir();
        $this->assertFalse(FileUtils::exists($dir));
    }
}
