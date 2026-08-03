<?php

namespace Tent\Tests\Models\ProcessingRequest;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Models\ProcessingRequest;

class ProcessingRequestCacheHashTest extends TestCase
{
    public function testCacheHashStartsAsNull()
    {
        $processingRequest = new ProcessingRequest();

        $this->assertNull($processingRequest->cacheHash());
    }

    public function testSetCacheHashSetsAndReturnsTheValue()
    {
        $processingRequest = new ProcessingRequest();

        $result = $processingRequest->setCacheHash('abc123');

        $this->assertEquals('abc123', $result);
    }

    public function testCacheHashReturnsTheMemoizedValue()
    {
        $processingRequest = new ProcessingRequest();
        $processingRequest->setCacheHash('abc123');

        $this->assertEquals('abc123', $processingRequest->cacheHash());
        $this->assertEquals('abc123', $processingRequest->cacheHash());
    }

    public function testSetCacheHashOverwritesThePreviousValue()
    {
        $processingRequest = new ProcessingRequest();
        $processingRequest->setCacheHash('first');
        $processingRequest->setCacheHash('second');

        $this->assertEquals('second', $processingRequest->cacheHash());
    }
}
