<?php

namespace Tent\Tests;

use PHPUnit\Framework\TestCase;
use Tent\Models\ForbiddenResponse;

class ForbiddenResponseTest extends TestCase
{
    public function testReturns403StatusAndDefaultBody()
    {
        $response = new ForbiddenResponse();
        $this->assertSame(403, $response->httpCode());
        $this->assertSame('Forbidden', $response->body());
        $this->assertContains('Content-Type: text/plain', $response->headerLines());
    }
}
