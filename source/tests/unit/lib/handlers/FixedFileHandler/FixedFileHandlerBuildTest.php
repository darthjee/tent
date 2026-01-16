<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Handlers\FixedFileHandler;

class FixedFileHandlerBuildTest extends TestCase
{
    public function testBuildCreatesFixedFileHandlerWithFile()
    {
        $handler = FixedFileHandler::build(['file' => './some/path/file.txt']);
        $this->assertInstanceOf(FixedFileHandler::class, $handler);

        // Reflection to check if the filePath property is set correctly
        $reflection = new \ReflectionClass($handler);
        $filePathProp = $reflection->getProperty('filePath');
        $filePathProp->setAccessible(true);
        $filePath = $filePathProp->getValue($handler);
        $this->assertEquals('./some/path/file.txt', $filePath);
    }
}
