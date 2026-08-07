<?php

namespace Tent\Tests\Models\ProcessingRequest;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Models\ProcessingRequest;
use Tent\Models\Request;

class ProcessingRequestSetQueryTest extends TestCase
{
    public function testSetQuerySetsAndReturnsValue()
    {
        $processingRequest = new ProcessingRequest();
        $result = $processingRequest->setQuery('a=1&b=2');

        $this->assertEquals('a=1&b=2', $result);
        $this->assertEquals('a=1&b=2', $processingRequest->query());
    }

    public function testSetQueryOverridesPreviousValue()
    {
        $processingRequest = new ProcessingRequest(['query' => 'a=1']);

        $processingRequest->setQuery('b=2');
        $this->assertEquals('b=2', $processingRequest->query());
    }

    public function testSetQueryOverridesRequestValue()
    {
        $request = new Request(['query' => 'a=1']);
        $processingRequest = new ProcessingRequest(['request' => $request]);

        $processingRequest->setQuery('b=2');
        $this->assertEquals('b=2', $processingRequest->query());
    }
}
