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
        $location = new FolderLocation('/var/www/');
           $this->assertEquals('/var/www/index.html', FileUtils::getFullPath('index.html', $location));
    }

    public function testGetFullPathWithTrailingSlashInBasePath()
    {
        $location = new FolderLocation('/var/www/');
           $this->assertEquals('/var/www/assets/style.css', FileUtils::getFullPath('assets/style.css', $location));
    }

    public function testGetFullPathWithNoTrailingSlashInBasePath()
    {
        $location = new FolderLocation('/var/www');
           $this->assertEquals('/var/www/assets/app.js', FileUtils::getFullPath('/assets/app.js', $location));
    }

    public function testGetFullPathWithNoSlashInBasePathOrFilePath()
    {
        $location = new FolderLocation('/var/www');
           $this->assertEquals('/var/www/app.js', FileUtils::getFullPath('app.js', $location));
    }
}
