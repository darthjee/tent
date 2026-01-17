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
     * @var string|null Optional name for the rule.
     */
    private $name;

    /**
     * Constructs a Rule.
     *
     * @param RequestHandler   $handler  The handler to process requests that match this rule.
     * @param RequestMatcher[] $matchers Array of matchers to validate requests.
     * @param string|null      $name     Optional name for the rule.
     */
    public function __construct(RequestHandler $handler, array $matchers = [], ?string $name = null)
    {
        $this->handler = $handler;
        $this->matchers = $matchers;
        $this->name = $name;
    }

    /**
     * Builds a Rule using named parameters for handler and matchers.
     *
     * Example:
     *   Rule::build([
     *     'handler' => ['type' => 'proxy', 'host' => 'http://api.com'],
     *     'matchers' => [
     *         ['method' => 'GET', 'uri' => '/persons', 'type' => 'exact']
     *     ]
     *   ])
     *
     * @param array $params Associative array with keys:
     *   - 'handler': array, parameters for RequestHandler::build.
     *   - 'matchers': array of associative arrays, each with keys 'method', 'uri', 'type'.
     *   - 'name': string|null, optional name for the rule.
     * @return Rule
     */
    public static function build(array $params): self
    {
        $handler = RequestHandler::build($params['handler'] ?? []);
        $name = $params['name'] ?? null;

        $matchers = $params['matchers'] ?? [];
        $matcherObjs = array_map(function ($matcher) {
            return RequestMatcher::build($matcher);
        }, $matchers);

        return new self($handler, $matcherObjs, $name);
    }
    /**
     * Returns the name of the rule, or null if not set.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return $this->name;
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
