<?php

namespace Tent\Models;

use Tent\RequestHandlers\RequestHandler;
use Tent\Matchers\RequestMatcher;
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
     * @param array $attributes Associative array with keys:
     *   - 'handler': RequestHandler, the handler to process requests that match this rule.
     *   - 'matchers': array of RequestMatcher, optional list of matchers to validate requests.
     *   - 'name': string|null, optional name for the rule.
     */
    public function __construct(array $attributes)
    {
        $this->handler = $attributes['handler'];
        $this->matchers = $attributes['matchers'] ?? [];
        $this->name = $attributes['name'] ?? null;
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

        $rule = new self([
            'handler' => $handler,
            'matchers' => RequestMatcher::buildMatchers($params['matchers'] ?? []),
            'name' => $name
        ]);

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
