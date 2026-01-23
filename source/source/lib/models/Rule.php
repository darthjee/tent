<?php

namespace Tent\Models;

use Tent\Handlers\RequestHandler;
use Tent\Models\RequestMatcher;
use Tent\Models\Server;
use Tent\Handlers\ProxyRequestHandler;
use Tent\Middlewares\Middleware;

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
    private RequestHandler $handler;

    /**
     * @var RequestMatcher[] List of matchers to validate if a request applies to this rule.
     */
    private array $matchers;

    /**
     * @var string|null Optional name for the rule.
     */
    private ?string $name;

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
    public function handler(): RequestHandler
    {
        return $this->handler;
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

        $rule = new self(
            $handler,
            RequestMatcher::buildMatchers($params['matchers'] ?? []),
            $name
        );

        $rule->buildMiddlewares($params['middlewares'] ?? []);

        return $rule;
    }

    /**
     * Builds and adds multiple Middlewares to the rule.
     *
     * @param array $attributes Array of associative arrays, each with keys for Middleware::build.
     * @return array all Middlewares.
     */
    protected function buildMiddlewares(array $attributes): array
    {
        return $this->handler()->buildMiddlewares($attributes);
    }

    /**
     * Builds and adds multiple RequestMatchers to the rule.
     *
     * @param array $attributes Array of associative arrays, each with keys 'method', 'uri', 'type'.
     * @return array all RequestMatchers.
     */
    protected function buildMatchers(array $attributes): array
    {
        foreach ($attributes as $attributes) {
            $this->buildMatcher($attributes);
        }
        return $this->matchers;
    }

    /**
     * Adds a RequestMatcher to the rule.
     *
     * @param array $matcherAttributes Associative array with keys 'method', 'uri', 'type'.
     * @return RequestMatcher The added RequestMatcher.
     */
    protected function buildMatcher(array $matcherAttributes): RequestMatcher
    {
        return $this->matchers[] = RequestMatcher::build($matcherAttributes);
    }

    /**
     * Checks if the given request matches any of the rule's matchers.
     *
     * @param RequestInterface $request The incoming HTTP request.
     * @return boolean True if any matcher applies to the request.
     */
    public function match(RequestInterface $request): bool
    {
        foreach ($this->matchers as $matcher) {
            if ($matcher->matches($request)) {
                return true;
            }
        }

        return false;
    }
}
