<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\RequestPathValidator;
use Tent\Request;

class DummyRequest
{
    private $url;
    public function __construct(string $url) { $this->url = $url; }
    public function requestUrl() { return $this->url; }
}

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
            $request = new Request([
                'requestUrl' => $path
            ]);

            $validator = new RequestPathValidator($request);
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
            $request = new Request([
                'requestUrl' => $path
            ]);

            $validator = new RequestPathValidator($request);
            $this->assertFalse($validator->isValid(), "Path '$path' should be invalid");
        }
    }
}
