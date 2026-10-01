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

    public function testBuildDefaultsConditionalToFalse()
    {
        $handler = StaticFileHandler::build(['location' => './some_folder']);

        $this->assertFalse($this->conditionalOf($handler));
    }

    public function testBuildParsesConditionalOption()
    {
        $handler = StaticFileHandler::build(['location' => './some_folder', 'conditional' => true]);

        $this->assertTrue($this->conditionalOf($handler));
    }

    public function testBuildParsesDisabledConditionalOption()
    {
        $handler = StaticFileHandler::build(['location' => './some_folder', 'conditional' => false]);

        $this->assertFalse($this->conditionalOf($handler));
    }

    private function conditionalOf(StaticFileHandler $handler): bool
    {
        $reflection = new \ReflectionClass($handler);
        $property = $reflection->getProperty('conditional');
        $property->setAccessible(true);

        return $property->getValue($handler);
    }
}
