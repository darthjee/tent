<?php

namespace Tent\Tests\Http;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Http\CurlHttpClient;

class CurlHttpClientInvalidMethodTest extends TestCase
{
    public function testRequestThrowsInvalidArgumentExceptionForUnsupportedMethod()
    {
        $client = new CurlHttpClient();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported HTTP method: INVALID');

        $client->request('INVALID', 'http://example.com', []);
    }

    public function testRequestThrowsInvalidArgumentExceptionForUnknownMethod()
    {
        $client = new CurlHttpClient();

        $this->expectException(\InvalidArgumentException::class);

        $client->request('FOOBAR', 'http://example.com', []);
    }
}
