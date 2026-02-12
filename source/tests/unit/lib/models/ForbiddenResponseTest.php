<?php

namespace Tent\Tests\Models;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Models\ForbiddenResponse;
use Tent\Models\Request;

class ForbiddenResponseTest extends TestCase
{
    public function testReturns403StatusAndDefaultBody()
    {
        $request = new Request([]);
        $response = new ForbiddenResponse($request);
        $this->assertSame(403, $response->httpCode());
        $this->assertSame('Forbidden', $response->body());
        $this->assertContains('Content-Type: text/plain', $response->headers());
    }
}
