<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Models\File;
use Tent\Models\FolderLocation;

class FileTest extends TestCase
{
    private $basePath;

    public function setUp(): void
    {
        $this->basePath = __DIR__ . '/fixtures/';
        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0777, true);
        }
        file_put_contents($this->basePath . 'test.txt', 'Hello World');
        file_put_contents($this->basePath . 'test.html', '<html></html>');
    }

    public function tearDown(): void
    {
        @unlink($this->basePath . 'test.txt');
        @unlink($this->basePath . 'test.html');
    }

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

    public function testContentReturnsFileContent()
    {
        $location = new \Tent\Models\FolderLocation($this->basePath);
        $file = new \Tent\Models\File('test.txt', $location);
        $this->assertEquals('Hello World', $file->content());
    }

    public function testHeadersReturnsContentTypeAndLength()
    {
        $location = new \Tent\Models\FolderLocation($this->basePath);
        $file = new \Tent\Models\File('test.txt', $location);
        $headers = $file->headers();
        $this->assertIsArray($headers);
        $this->assertNotEmpty($headers);
        $this->assertStringContainsString('Content-Type:', $headers[0]);
        $this->assertStringContainsString('Content-Length:', $headers[1]);
    }
    
    public function testExistsReturnsTrueForExistingFile()
    {
        $location = new \Tent\Models\FolderLocation($this->basePath);
        $file = new \Tent\Models\File('test.txt', $location);
        $this->assertTrue($file->exists());
    }

    public function testExistsReturnsFalseForNonexistentFile()
    {
        $location = new \Tent\Models\FolderLocation($this->basePath);
        $file = new \Tent\Models\File('notfound.txt', $location);
        $this->assertFalse($file->exists());
    }
}
