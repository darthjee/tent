<?php

require_once __DIR__ . '/../../../../source/lib/service/RequestProcessor.php';
require_once __DIR__ . '/../../../../source/lib/Configuration.php';
require_once __DIR__ . '/../../../../source/lib/handlers/MissingRequestHandler.php';

use PHPUnit\Framework\TestCase;
use Tent\RequestProcessor;
use Tent\Configuration;
use Tent\Rule;

class DummyRequestHandler {
    public $handledRequest = null;
    public function handleRequest($request) {
        $this->handledRequest = $request;
        return "handled: " . $request;
    }
}

class DummyRule {
    private $shouldMatch;
    private $handler;
    public function __construct($shouldMatch, $handler) {
        $this->shouldMatch = $shouldMatch;
        $this->handler = $handler;
    }
    public function match($request) {
        return $this->shouldMatch;
    }
    public function handler() {
        return $this->handler;
    }
}

class RequestProcessorTest extends TestCase {
    protected function setUp(): void {
        // Reset rules before each test
        $ref = new ReflectionClass(Configuration::class);
        $prop = $ref->getProperty('rules');
        $prop->setAccessible(true);
        $prop->setValue([]);
    }

    public function testHandleReturnsHandlerResult() {
        $handler = new DummyRequestHandler();
        $rule = new DummyRule(true, $handler);
        Configuration::addRule($rule);
        $request = 'test-request';
        $result = RequestProcessor::handleRequest($request);
        $this->assertEquals('handled: test-request', $result);
        $this->assertEquals($request, $handler->handledRequest);
    }

    public function testHandleReturnsNullIfNoRuleMatches() {
        $rule = new DummyRule(false, new DummyRequestHandler());
        Configuration::addRule($rule);
        $result = RequestProcessor::handleRequest('no-match');
        $this->assertNull($result);
    }
}
