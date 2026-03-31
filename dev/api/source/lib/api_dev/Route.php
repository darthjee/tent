<?php

namespace ApiDev;

/**
 * Represents a route pattern for matching HTTP requests.
 *
 * Defines a route with optional HTTP method and URL path constraints
 * for matching incoming requests.
 */
class Route
{
    /**
     * @var string|null The HTTP method to match (e.g., GET, POST), or null for any method
     */
    private ?string $requestMethod;

    /**
     * @var string|null The URL path to match, or null for any path
     */
    private ?string $path;

    /**
     * Creates a new Route instance.
     *
     * @param string|null $requestMethod The HTTP method to match, or null to match any method
     * @param string|null $path The URL path to match, or null to match any path
     */
    public function __construct(?string $requestMethod, ?string $path)
    {
        $this->requestMethod = $requestMethod;
        $this->path = $path;
    }

    /**
     * Checks if this route matches the given request.
     *
     * @param RequestInterface $request The HTTP request to match against
     * @return bool True if the route matches the request, false otherwise
     */
    public function matches(RequestInterface $request): bool
    {
        return $this->matchRequestMethod($request) && $this->matchPath($request);
    }

    /**
     * Checks if the request's HTTP method matches this route's method.
     *
     * @param RequestInterface $request The HTTP request to check
     * @return bool True if methods match or route method is null (matches any)
     */
    private function matchRequestMethod(RequestInterface $request): bool
    {
        return $this->requestMethod === null || $request->requestMethod() === $this->requestMethod;
    }

    /**
     * Checks if the request's URL path matches this route's path.
     *
     * Supports parameterised segments using the :param syntax
     * (e.g. /persons/:id/photo.json).
     *
     * @param RequestInterface $request The HTTP request to check
     * @return bool True if paths match or route path is null (matches any)
     */
    private function matchPath(RequestInterface $request): bool
    {
        if ($this->path === null) {
            return true;
        }

        if (strpos($this->path, ':') === false) {
            return $request->requestUrl() === $this->path;
        }

        return (bool) preg_match($this->buildPattern(), $request->requestUrl());
    }

    /**
     * Converts a parameterised path pattern into a regex.
     *
     * Each :param segment is replaced with [^/]+ so it matches any
     * single path segment. All other characters are regex-escaped.
     *
     * @return string A regex pattern (e.g. #^/persons/[^/]+/photo\.json$#)
     */
    private function buildPattern(): string
    {
        $parts = preg_split('/(:[^\/]+)/', $this->path, -1, PREG_SPLIT_DELIM_CAPTURE);
        $regexParts = array_map(function (string $part): string {
            return str_starts_with($part, ':') ? '[^/]+' : preg_quote($part, '#');
        }, $parts);
        return '#^' . implode('', $regexParts) . '$#';
    }
}
