<?php

namespace Tent\Tests\Service\ResponseContentReader;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Service\ResponseContentReader;
use Tent\Content\File;
use Tent\Models\Request;
use Tent\Models\FolderLocation;
use Tent\Models\Response;
use Tent\Exceptions\FileNotFoundException;
use Tent\Exceptions\InvalidFilePathException;

class ResponseContentReaderWithFileTest extends TestCase
{
    private $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/tent_filereader_' . uniqid();
        mkdir($this->testDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
    }

    private function removeDirectory($dir)
    {
        if (!file_exists($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testReadFileToResponseReturnsFileContent()
    {
        file_put_contents($this->testDir . '/test.txt', 'Hello World');
        $location = new FolderLocation($this->testDir);
        $request = new Request(['requestPath' => '/test.txt']);
        $file = new File('/test.txt', $location);
        $reader = new ResponseContentReader($request, $file);

        $response = $reader->getResponse();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->httpCode());
        $this->assertEquals('Hello World', $response->body());
        $this->assertContains('Content-Type: text/plain', $response->headers());
    }

    public function testReadFileToResponseThrowsFileNotFoundException()
    {
        $location = new FolderLocation($this->testDir);
        $request = new Request(['requestPath' => '/nonexistent.txt']);
        $file = new File('/nonexistent.txt', $location);
        $reader = new ResponseContentReader($request, $file);

        $this->expectException(FileNotFoundException::class);
        $reader->getResponse();
    }

    public function testReadFileToResponseThrowsInvalidFilePathException()
    {
        $location = new FolderLocation($this->testDir);
        $request = new Request(['requestPath' => '../etc/passwd']);
        $file = new File('../etc/passwd', $location);
        $reader = new ResponseContentReader($request, $file);

        $this->expectException(InvalidFilePathException::class);
        $reader->getResponse();
    }
}
