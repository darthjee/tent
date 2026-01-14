<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\ContentType;

class ContentTypeTest extends TestCase
{
    public function testReturnsHtmlContentType()
    {
        $filePath = './tests/fixtures/content.html';
        $this->assertEquals('text/html', ContentType::getContentType($filePath));
    }

    public function testReturnsJsonContentType()
    {
        $filePath = './tests/fixtures/data.json';
        $this->assertEquals('application/json', ContentType::getContentType($filePath));
    }

    public function testReturnsGifContentType()
    {
        $filePath = './tests/fixtures/image.gif';
        $this->assertEquals('image/gif', ContentType::getContentType($filePath));
    }

    public function testReturnsCssContentType()
    {
        $filePath = './tests/fixtures/style.css';
        $this->assertEquals('text/css', ContentType::getContentType($filePath));
    }
}
