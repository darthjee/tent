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
        $this->assertEquals('/var/www/index.html', FileUtils::getFullPath('index.html', '/var/www/'));
    }

    public function testGetFullPathWithTrailingSlashInBasePath()
    {
        $this->assertEquals('/var/www/assets/style.css', FileUtils::getFullPath('assets/style.css', '/var/www/'));
    }

    public function testGetFullPathWithNoTrailingSlashInBasePath()
    {
        $this->assertEquals('/var/www/assets/app.js', FileUtils::getFullPath('/assets/app.js', '/var/www'));
    }

    public function testGetFullPathWithNoSlashInBasePathOrFilePath()
    {
        $this->assertEquals('/var/www/app.js', FileUtils::getFullPath('app.js', '/var/www'));
    }
}
