<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Handlers\RequestPathValidator;
use Tent\Models\Request;

class RequestPathValidatorTest extends TestCase
{
    public function testValidPaths()
    {
        $validPaths = [
            '/',
            '/assets/file.js',
            '/index.html',
            '/css/style.css',
            '/api/persons',
            '/node_modules/react.js',
        ];
        foreach ($validPaths as $path) {
            $validator = new RequestPathValidator($path);
            $this->assertTrue($validator->isValid(), "Path '$path' should be valid");
        }
    }

    public function testInvalidPaths()
    {
        $invalidPaths = [
            '/../etc/passwd',
            '/assets/../secret.txt',
            '/..',
            '/css\\evil.css',
            '/assets\\..\\file.js',
            '.../file.js',
            '/api/..//persons',
        ];
        foreach ($invalidPaths as $path) {
            $validator = new RequestPathValidator($path);
            $this->assertFalse($validator->isValid(), "Path '$path' should be invalid");
        }
    }
}
