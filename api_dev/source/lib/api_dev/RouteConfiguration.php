<?php

namespace ApiDev;

class RouteConfiguration
{
    private $route;
    private $endpoint;

    public function __construct($requestMethod, $path, $endpoint)
    {
        $this->route = new Route($requestMethod, $path);
        $this->endpoint = $endpoint;
    }

    public function match($request)
    {
        return $this->route->matches($request);
    }

    public function handle($request)
    {
        $endpointInstance = new $this->endpoint($request);
        return $endpointInstance->handle();
    }
}
