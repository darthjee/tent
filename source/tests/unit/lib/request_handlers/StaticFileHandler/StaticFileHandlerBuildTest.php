<?php

namespace Tent\Tests\RequestHandlers\StaticFileHandler;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\RequestHandlers\StaticFileHandler;

class StaticFileHandlerBuildTest extends TestCase
{
    public function testBuildCreatesStaticFileHandlerWithLocation()
    {
        $handler = StaticFileHandler::build(['location' => './some_folder']);
        $this->assertInstanceOf(StaticFileHandler::class, $handler);

        // Reflection to check if the folderLocation property is set correctly
        $reflection = new \ReflectionClass($handler);
        $folderLocationProp = $reflection->getProperty('folderLocation');
        $folderLocationProp->setAccessible(true);
        $folderLocation = $folderLocationProp->getValue($handler);
        $this->assertEquals('./some_folder', $folderLocation->basePath());
    }
}
