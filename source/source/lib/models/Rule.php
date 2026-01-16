<?php

namespace Tent\Models;

use Tent\Handlers\RequestHandler;
use Tent\Models\RequestMatcher;
use Tent\Models\Server;
use Tent\Handlers\ProxyRequestHandler;

/**
 * Represents a routing rule for processing HTTP requests.
 *
 * A Rule contains multiple RequestMatchers to validate if a request applies to this rule.
 * When a request matches, the Rule provides the RequestHandler to process the request.
 */
class Rule
{
    /**
     * @var RequestHandler The handler used to process matching requests.
     */
    private $handler;

    /**
     * @var RequestMatcher[] List of matchers to validate if a request applies to this rule.
     */
    private $matchers;

    /**
     * Constructs a Rule.
     *
     * @param RequestHandler   $handler  The handler to process requests that match this rule.
     * @param RequestMatcher[] $matchers Array of matchers to validate requests.
     */
    public function __construct(RequestHandler $handler, array $matchers = [])
    {
        $this->handler = $handler;
        $this->matchers = $matchers;
    }

    /**
     * Builds a Rule for proxying to a target host with simplified matcher definitions.
     *
     * Example:
     *   Rule::build('http://api.com', [['GET', '/index.html', 'exact']])
     *
     * @param string $targetHost The target host for the proxy handler.
     * @param array $matchers Array of arrays, each with [method, uri, matchType].
     * @return Rule
     */
    public static function build(string $targetHost, array $matchers): self
    {
        $matcherObjs = array_map(function($m) {
            return new RequestMatcher($m[0] ?? null, $m[1] ?? null, $m[2] ?? 'exact');
        }, $matchers);
        $handler = new ProxyRequestHandler(new Server($targetHost));
        return new self($handler, $matcherObjs);
    }

    /**
     * Returns the RequestHandler for this rule.
     *
     * @return RequestHandler
     */
    public function handler()
    {
        return $this->handler;
    }

    /**
     * Checks if the given request matches any of the rule's matchers.
     *
     * @param Request $request The incoming HTTP request.
     * @return boolean True if any matcher applies to the request.
     */
    public function match(Request $request)
    {
        foreach ($this->matchers as $matcher) {
            if ($matcher->matches($request)) {
                return true;
            }
        }

        return false;
    }
}
