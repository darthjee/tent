<?php

namespace Tent\Tests\Models\ProcessingRequest;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Models\ProcessingRequest;
use Tent\Models\Request;

class ProcessingRequestUploadedFilesTest extends TestCase
{
    public function testUploadedFilesDelegatesToRequest()
    {
        $files = [
            'photo' => [
                'name' => 'photo.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/php123',
                'error' => 0,
                'size' => 2048,
            ]
        ];
        $request = new Request(['uploadedFiles' => $files]);
        $processingRequest = new ProcessingRequest(['request' => $request]);

        $this->assertEquals($files, $processingRequest->uploadedFiles());
    }

    public function testUploadedFilesCachesResult()
    {
        $files = [
            'photo' => ['name' => 'a.jpg', 'type' => 'image/jpeg', 'tmp_name' => '/tmp/x', 'error' => 0, 'size' => 1],
        ];
        $request = new Request(['uploadedFiles' => $files]);
        $processingRequest = new ProcessingRequest(['request' => $request]);

        $first = $processingRequest->uploadedFiles();
        $second = $processingRequest->uploadedFiles();

        $this->assertSame($first, $second);
    }

    public function testUploadedFilesReturnsEmptyWhenNoRequest()
    {
        $processingRequest = new ProcessingRequest([]);

        $this->assertEquals([], $processingRequest->uploadedFiles());
    }

    public function testUploadedFilesCanBeOverridden()
    {
        $files = [
            'doc' => [
                'name' => 'file.pdf', 'type' => 'application/pdf', 'tmp_name' => '/tmp/y', 'error' => 0, 'size' => 512,
            ],
        ];
        $processingRequest = new ProcessingRequest(['uploadedFiles' => $files]);

        $this->assertEquals($files, $processingRequest->uploadedFiles());
    }

    public function testPostFieldsDelegatesToRequest()
    {
        $fields = ['name' => 'Alice', 'age' => '30'];
        $request = new Request(['postFields' => $fields]);
        $processingRequest = new ProcessingRequest(['request' => $request]);

        $this->assertEquals($fields, $processingRequest->postFields());
    }

    public function testPostFieldsReturnsEmptyWhenNoRequest()
    {
        $processingRequest = new ProcessingRequest([]);

        $this->assertEquals([], $processingRequest->postFields());
    }

    public function testPostFieldsCanBeOverridden()
    {
        $fields = ['key' => 'val'];
        $processingRequest = new ProcessingRequest(['postFields' => $fields]);

        $this->assertEquals($fields, $processingRequest->postFields());
    }
}
