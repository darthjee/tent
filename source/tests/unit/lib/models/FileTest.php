<?php

namespace Tent\Tests\Models;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Content\File;
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
        $location = new FolderLocation($this->basePath);
        $file = new File('test.txt', $location);
        $this->assertEquals('Hello World', $file->content());
    }

    public function testHeadersReturnsContentTypeAndLength()
    {
        $location = new FolderLocation($this->basePath);
        $file = new File('test.txt', $location);
        $headers = $file->headers();
        $this->assertIsArray($headers);
        $this->assertNotEmpty($headers);
        $this->assertStringContainsString('Content-Type:', $headers[0]);
        $this->assertStringContainsString('Content-Length:', $headers[1]);
    }

    public function testExistsReturnsTrueForExistingFile()
    {
        $location = new FolderLocation($this->basePath);
        $file = new File('test.txt', $location);
        $this->assertTrue($file->exists());
    }

    public function testExistsReturnsFalseForNonexistentFile()
    {
        $location = new FolderLocation($this->basePath);
        $file = new File('notfound.txt', $location);
        $this->assertFalse($file->exists());
    }

    public function testEtagIsQuotedMd5OfSizeAndMtime()
    {
        $path = $this->basePath . 'test.txt';
        touch($path, 1700000000);
        $file = new File('test.txt', new FolderLocation($this->basePath));

        $expected = '"' . md5(filesize($path) . '-1700000000') . '"';
        $this->assertEquals($expected, $file->etag());
        $this->assertMatchesRegularExpression('/^"[0-9a-f]{32}"$/', $file->etag());
    }

    public function testEtagIsStableForUnchangedFile()
    {
        $file = new File('test.txt', new FolderLocation($this->basePath));

        $this->assertEquals($file->etag(), $file->etag());
    }

    public function testEtagChangesWhenFileIsRewritten()
    {
        $path = $this->basePath . 'test.txt';
        touch($path, 1700000000);
        $file = new File('test.txt', new FolderLocation($this->basePath));
        $before = $file->etag();

        file_put_contents($path, 'Hello World, again');
        touch($path, 1700000000);

        $this->assertNotEquals($before, $file->etag());
    }

    public function testEtagChangesWhenMtimeChanges()
    {
        $path = $this->basePath . 'test.txt';
        touch($path, 1700000000);
        $file = new File('test.txt', new FolderLocation($this->basePath));
        $before = $file->etag();

        touch($path, 1700000100);

        $this->assertNotEquals($before, $file->etag());
    }

    public function testLastModifiedReturnsMtime()
    {
        touch($this->basePath . 'test.txt', 1700000000);
        $file = new File('test.txt', new FolderLocation($this->basePath));

        $this->assertSame(1700000000, $file->lastModified());
    }

    public function testLastModifiedHeaderIsImfFixdate()
    {
        touch($this->basePath . 'test.txt', 1700000000);
        $file = new File('test.txt', new FolderLocation($this->basePath));

        $this->assertEquals('Tue, 14 Nov 2023 22:13:20 GMT', $file->lastModifiedHeader());
    }

    public function testValidatorHeaders()
    {
        touch($this->basePath . 'test.txt', 1700000000);
        $file = new File('test.txt', new FolderLocation($this->basePath));

        $this->assertEquals(
            [
                'ETag: ' . $file->etag(),
                'Last-Modified: Tue, 14 Nov 2023 22:13:20 GMT'
            ],
            $file->validatorHeaders()
        );
    }

    public function testHttpCodeReturns200()
    {
        $location = new FolderLocation($this->basePath);
        $file = new File('test.txt', $location);
        $this->assertEquals(200, $file->httpCode());
    }
}
