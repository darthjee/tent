<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Utils\FilePath;
use Tent\Utils\FileUtils;
use Tent\Models\FolderLocation;

class FileUtilsGeneralTest extends TestCase
{
    public function testGetFullPathConcatenatesBasePathAndFilePath()
    {
        $this->assertEquals('/var/www/index.html', FileUtils::getFullPath(['/var/www/', 'index.html']));
    }

    public function testGetFullPathWithTrailingSlashInBasePath()
    {
        $this->assertEquals('/var/www/assets/style.css', FileUtils::getFullPath(['/var/www/', '/assets/style.css']));
    }

    public function testGetFullPathWithNoTrailingSlashInBasePath()
    {
        $this->assertEquals('/var/www/assets/app.js', FileUtils::getFullPath(['/var/www', '/assets/app.js']));
    }

    public function testGetFullPathWithNoSlashInBasePathOrFilePath()
    {
        $this->assertEquals('/var/www/app.js', FileUtils::getFullPath(['/var/www', 'app.js']));
    }

    public function testGetFullPathWithThreeElementsBasic()
    {
        $this->assertEquals('/a/b/c', FileUtils::getFullPath(['/a/', '/b/', '/c']));
    }

    public function testGetFullPathWithThreeElementsTrailingSlashes()
    {
        $this->assertEquals('/a/b/c', FileUtils::getFullPath(['/a/', '/b/', '/c/']));
    }

    public function testGetFullPathWithThreeElementsNoSlashes()
    {
        $this->assertEquals('a/b/c', FileUtils::getFullPath(['a', 'b', 'c']));
    }

    public function testGetFullPathWithThreeElementsMixedSlashes()
    {
        $this->assertEquals('/a/b/c', FileUtils::getFullPath(['/a/', 'b/', '/c']));
        $this->assertEquals('a/b/c', FileUtils::getFullPath(['a/', '/b/', 'c/']));
    }
}
