<?php

namespace ApiDev;

class Route
{
    private $requestMethod;
    private $path;

    public function __construct($requestMethod, $path)
    {
        $this->requestMethod = $requestMethod;
        $this->path = $path;
    }

    public function matches($request)
    {
        return $this->matchRequestMethod($request) && $this->matchPath($request);
    }

    private function matchRequestMethod($request)
    {
        return $this->requestMethod === null || $request->requestMethod() === $this->requestMethod;
    }

    private function matchPath($request)
    {
        return $this->path === null || $request->requestUrl() === $this->path;
    }
}
