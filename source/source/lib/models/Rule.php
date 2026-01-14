<?php

namespace Tent;

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
     * @param RequestHandler $handler The handler to process requests that match this rule.
     * @param RequestMatcher[] $matchers Array of matchers to validate requests.
     */
    public function __construct($handler, $matchers = [])
    {
        $this->handler = $handler;
        $this->matchers = $matchers;
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
     * @return bool True if any matcher applies to the request.
     */
    public function match($request)
    {
        foreach ($this->matchers as $matcher) {
            if ($matcher->matches($request)) {
                return true;
            }
        }

        return false;
    }
}
