<?php

namespace Tent\Tests\Cache\QueryRequestHasher;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Cache\QueryRequestHasher;
use Tent\Models\ProcessingRequest;
use Tent\Models\Request;

class QueryRequestHasherHashTest extends TestCase
{
    public function testHashReturnsSha256OfTheRequestQuery()
    {
        $request = new ProcessingRequest(['query' => 'a=1&b=2']);
        $hasher = new QueryRequestHasher();

        $expected = hash('sha256', 'a=1&b=2');

        $this->assertEquals($expected, $hasher->hash($request));
    }

    public function testHashReturnsSha256OfEmptyQueryWhenQueryIsAbsent()
    {
        $request = new ProcessingRequest();
        $hasher = new QueryRequestHasher();

        $expected = hash('sha256', '');

        $this->assertEquals($expected, $hasher->hash($request));
    }

    public function testHashWorksWithAnyRequestInterfaceImplementation()
    {
        $request = new Request(['query' => 'foo=bar']);
        $hasher = new QueryRequestHasher();

        $expected = hash('sha256', 'foo=bar');

        $this->assertEquals($expected, $hasher->hash($request));
    }
}
