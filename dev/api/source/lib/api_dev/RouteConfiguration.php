<?php

namespace ApiDev;

/**
 * Combines a route pattern with an endpoint handler.
 * 
 * Represents a complete route configuration that can match incoming requests
 * and dispatch them to the appropriate endpoint handler.
 */
class RouteConfiguration
{
    /**
     * @var Route The route pattern for matching requests
     */
    private $route;
    
    /**
     * @var string The fully qualified class name of the endpoint handler
     */
    private $endpoint;

    /**
     * Creates a new RouteConfiguration instance.
     * 
     * @param string|null $requestMethod The HTTP method to match (e.g., GET, POST)
     * @param string|null $path The URL path to match
     * @param string $endpoint The fully qualified class name of the endpoint handler
     */
    public function __construct(?string $requestMethod, ?string $path, string $endpoint)
    {
        $this->route = new Route($requestMethod, $path);
        $this->endpoint = $endpoint;
    }

    /**
     * Checks if this route configuration matches the given request.
     * 
     * @param RequestInterface $request The HTTP request to match against
     * @return bool True if the route matches the request, false otherwise
     */
    public function match(RequestInterface $request): bool
    {
        return $this->route->matches($request);
    }

    /**
     * Handles the request by instantiating and invoking the endpoint.
     * 
     * @param RequestInterface $request The HTTP request to handle
     * @return Response The HTTP response from the endpoint
     */
    public function handle(RequestInterface $request): Response
    {
        $endpointInstance = new $this->endpoint($request);
        return $endpointInstance->handle();
    }
}
