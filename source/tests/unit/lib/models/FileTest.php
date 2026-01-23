<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Models\File;
use Tent\Models\FolderLocation;

class FileTest extends TestCase
{
    public function testFullPathConcatenatesBasePathAndFilePath()
    {
        $location = new FolderLocation('/var/www/');
        $file = new File('index.html', $location);
        $this->assertEquals('/var/www/index.html', $file->fullPath());
    }

    public function testFullPathWithTrailingSlashInBasePath()
    {
        $location = new FolderLocation('/var/www/');
        $file = new File('assets/style.css', $location);
        $this->assertEquals('/var/www/assets/style.css', $file->fullPath());
    }

    public function testFullPathWithNoTrailingSlashInBasePath()
    {
        $location = new FolderLocation('/var/www');
        $file = new File('/assets/app.js', $location);
        $this->assertEquals('/var/www/assets/app.js', $file->fullPath());
    }
}
