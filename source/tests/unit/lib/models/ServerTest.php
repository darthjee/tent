<?php

namespace Tent\Tests\Models;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Models\Server;

class ServerTest extends TestCase
{
    public function testTargetHostReturnsTheTargetHost()
    {
        $server = new Server('http://api.example.com:8080');

        $this->assertEquals('http://api.example.com:8080', $server->targetHost());
    }

    public function testConstructorAcceptsTargetHost()
    {
        $server = new Server('http://backend:80');

        $this->assertEquals('http://backend:80', $server->targetHost());
    }

    public function testFullUrlBuildsUrlWithPathOnly()
    {
        $server = new Server('http://api.example.com');

        $url = $server->fullUrl('/persons');

        $this->assertEquals('http://api.example.com/persons', $url);
    }

    public function testFullUrlNormalizesSlashesInHostAndPath()
    {
        $server = new Server('http://api.example.com/');

        $url = $server->fullUrl('/persons');

        $this->assertEquals('http://api.example.com/persons', $url);
    }

    public function testFullUrlHandlesPathWithoutLeadingSlash()
    {
        $server = new Server('http://api.example.com');

        $url = $server->fullUrl('persons');

        $this->assertEquals('http://api.example.com/persons', $url);
    }

    public function testFullUrlWithQueryString()
    {
        $server = new Server('http://api.example.com');

        $url = $server->fullUrl('/persons', 'page=1&limit=10');

        $this->assertEquals('http://api.example.com/persons?page=1&limit=10', $url);
    }

    public function testFullUrlWithQueryStringAndLeadingQuestion()
    {
        $server = new Server('http://api.example.com');

        $url = $server->fullUrl('/persons', '?page=1&limit=10');

        $this->assertEquals('http://api.example.com/persons?page=1&limit=10', $url);
    }

    public function testFullUrlNullQueryStringOmitted()
    {
        $server = new Server('http://api.example.com');

        $url = $server->fullUrl('/persons', null);

        $this->assertEquals('http://api.example.com/persons', $url);
    }

    public function testFullUrlWithComplexPath()
    {
        $server = new Server('http://api.example.com/v1');

        $url = $server->fullUrl('/persons/123/details');

        $this->assertEquals('http://api.example.com/v1/persons/123/details', $url);
    }

    public function testFullUrlWithPortNumber()
    {
        $server = new Server('http://api.example.com:3000');

        $url = $server->fullUrl('/api/users');

        $this->assertEquals('http://api.example.com:3000/api/users', $url);
    }

    public function testFullUrlWithTrailingSlashInHost()
    {
        $server = new Server('http://api.example.com/');

        $url = $server->fullUrl('/api', 'foo=bar');

        $this->assertEquals('http://api.example.com/api?foo=bar', $url);
    }

    public function testFullUrlEmptyPath()
    {
        $server = new Server('http://api.example.com');

        $url = $server->fullUrl('');

        $this->assertEquals('http://api.example.com/', $url);
    }

    public function testFullUrlWithPathContainingSpecialCharacters()
    {
        $server = new Server('http://api.example.com');

        $url = $server->fullUrl('/api/search');

        $this->assertEquals('http://api.example.com/api/search', $url);
    }
}
