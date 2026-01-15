<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Models\FolderLocation;

class FolderLocationTest extends TestCase
{
    public function testBasePathReturnsTheBasePath()
    {
        $location = new FolderLocation('/var/www/html');

        $this->assertEquals('/var/www/html', $location->basePath());
    }

    public function testConstructorAcceptsBasePath()
    {
        $location = new FolderLocation('/path/to/files');

        $this->assertEquals('/path/to/files', $location->basePath());
    }
}
