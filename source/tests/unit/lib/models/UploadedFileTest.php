<?php

namespace Tent\Tests\Models;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Models\UploadedFile;

class UploadedFileTest extends TestCase
{
    public function testToCurlFileReturnsCurlFile()
    {
        $file = [
            'tmp_name' => '/tmp/phpABCDEF',
            'type'     => 'image/jpeg',
            'name'     => 'photo.jpg',
        ];

        $uploadedFile = new UploadedFile($file);
        $curlFile = $uploadedFile->toCurlFile();

        $this->assertInstanceOf(\CURLFile::class, $curlFile);
    }

    public function testToCurlFileUsesCorrectTmpName()
    {
        $file = [
            'tmp_name' => '/tmp/phpXYZ789',
            'type'     => 'image/png',
            'name'     => 'avatar.png',
        ];

        $uploadedFile = new UploadedFile($file);
        $curlFile = $uploadedFile->toCurlFile();

        $this->assertSame('/tmp/phpXYZ789', $curlFile->getFilename());
    }

    public function testToCurlFileUsesCorrectMimeType()
    {
        $file = [
            'tmp_name' => '/tmp/phpABCDEF',
            'type'     => 'application/pdf',
            'name'     => 'document.pdf',
        ];

        $uploadedFile = new UploadedFile($file);
        $curlFile = $uploadedFile->toCurlFile();

        $this->assertSame('application/pdf', $curlFile->getMimeType());
    }

    public function testToCurlFileUsesCorrectPostname()
    {
        $file = [
            'tmp_name' => '/tmp/phpABCDEF',
            'type'     => 'text/plain',
            'name'     => 'notes.txt',
        ];

        $uploadedFile = new UploadedFile($file);
        $curlFile = $uploadedFile->toCurlFile();

        $this->assertSame('notes.txt', $curlFile->getPostFilename());
    }

    public function testToCurlFileDefaultsTypeToEmptyString()
    {
        $file = [
            'tmp_name' => '/tmp/phpABCDEF',
        ];

        $uploadedFile = new UploadedFile($file);
        $curlFile = $uploadedFile->toCurlFile();

        $this->assertSame('', $curlFile->getMimeType());
    }

    public function testToCurlFileDefaultsNameToEmptyString()
    {
        $file = [
            'tmp_name' => '/tmp/phpABCDEF',
        ];

        $uploadedFile = new UploadedFile($file);
        $curlFile = $uploadedFile->toCurlFile();

        $this->assertSame('', $curlFile->getPostFilename());
    }
}
