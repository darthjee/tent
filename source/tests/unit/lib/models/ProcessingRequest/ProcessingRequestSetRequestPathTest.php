<?php

namespace Tent\Tests\Models\ProcessingRequest;

use PHPUnit\Framework\TestCase;
use Tent\Models\ProcessingRequest;
use Tent\Models\Request;

class ProcessingRequestSetRequestPathTest extends TestCase
{
    public function testSetRequestPathSetsAndReturnsValue()
    {
        $processingRequest = new ProcessingRequest();
        $result = $processingRequest->setRequestPath('/my/custom/path');

        $this->assertEquals('/my/custom/path', $result);
        $this->assertEquals('/my/custom/path', $processingRequest->requestPath());
    }

    public function testSetRequestPathOverridesPreviousValue()
    {
        $processingRequest = new ProcessingRequest(['requestPath' => '/initial/path']);

        $processingRequest->setRequestPath('/new/path');
        $this->assertEquals('/new/path', $processingRequest->requestPath());
    }

    public function testSetRequestPathOverridesRequestValue()
    {
        $request = new Request(['requestPath' => '/initial/path']);
        $processingRequest = new ProcessingRequest(['request' => $request]);

        $processingRequest->setRequestPath('/new/path');
        $this->assertEquals('/new/path', $processingRequest->requestPath());
    }
}
