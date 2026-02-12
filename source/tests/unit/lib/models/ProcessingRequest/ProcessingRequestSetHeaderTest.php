<?php

namespace Tent\Tests\Models\ProcessingRequest;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Models\ProcessingRequest;
use Tent\Models\Request;

class ProcessingRequestSetHeaderTest extends TestCase
{
    public function testSetHeaderSetsValueInCachedHeaders()
    {
        $request = new Request([
            'headers' => ['X-Test' => 'old', 'Content-Type' => 'text/plain']
        ]);
        $processingRequest = new ProcessingRequest(['request' => $request]);

        // Set a new header value
        $result = $processingRequest->setHeader('X-Test', 'new-value');
        $this->assertEquals('new-value', $result);

        // The cached headers should reflect the change
        $headers = $processingRequest->headers();
        $this->assertEquals('new-value', $headers['X-Test']);
        $this->assertEquals('text/plain', $headers['Content-Type']);
    }

    public function testSetHeaderAddsNewHeader()
    {
        $request = new Request([
            'headers' => ['Content-Type' => 'text/html']
        ]);
        $processingRequest = new ProcessingRequest(['request' => $request]);

        // Add a new header
        $result = $processingRequest->setHeader('X-New', 'value123');
        $this->assertEquals('value123', $result);

        $headers = $processingRequest->headers();
        $this->assertEquals('value123', $headers['X-New']);
        $this->assertEquals('text/html', $headers['Content-Type']);
    }

    public function testSetHeaderWorksWithInitialHeadersParam()
    {
        $processingRequest = new ProcessingRequest(['headers' => ['X-Initial' => 'abc']]);
        $result = $processingRequest->setHeader('X-Initial', 'xyz');
        $this->assertEquals('xyz', $result);
        $headers = $processingRequest->headers();
        $this->assertEquals('xyz', $headers['X-Initial']);
    }
}
