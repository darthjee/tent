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
