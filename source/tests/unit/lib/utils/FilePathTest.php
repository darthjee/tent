<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Utils\FilePath;
use Tent\Models\FolderLocation;

class FilePathTest extends TestCase
{
    public function testGetFullPathConcatenatesBasePathAndFilePath()
    {
        $location = new FolderLocation('/var/www/');
        $this->assertEquals('/var/www/index.html', FilePath::getFullPath('index.html', $location));
    }

    public function testGetFullPathWithTrailingSlashInBasePath()
    {
        $location = new FolderLocation('/var/www/');
        $this->assertEquals('/var/www/assets/style.css', FilePath::getFullPath('assets/style.css', $location));
    }

    public function testGetFullPathWithNoTrailingSlashInBasePath()
    {
        $location = new FolderLocation('/var/www');
        $this->assertEquals('/var/www/assets/app.js', FilePath::getFullPath('/assets/app.js', $location));
    }
    
    public function testGetFullPathWithNoSlashInBasePathOrFilePath()
    {
        $location = new FolderLocation('/var/www');
        $this->assertEquals('/var/www/app.js', FilePath::getFullPath('app.js', $location));
    }
}
