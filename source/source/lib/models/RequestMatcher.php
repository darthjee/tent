<?php

namespace Tent;

class RequestMatcher
{
    private $requestMethod;
    private $requestUri;
    private $matchType;

    public function __construct($requestMethod = null, $requestUri = null, $matchType = 'exact')
    {
        $this->requestMethod = $requestMethod;
        $this->requestUri = $requestUri;
        $this->matchType = $matchType;
    }

    public function matches($request)
    {
        return $this->matchRequestMethod($request) && $this->matchRequestUri($request);
    }

    private function matchRequestMethod($request)
    {
        return $this->requestMethod == null || $request->requestMethod() == $this->requestMethod;
    }

    private function matchRequestUri($request)
    {
        if ($this->requestUri == null) {
            return true;
        }

        $requestUrl = $request->requestUrl();

        if ($this->matchType === 'exact') {
            return $requestUrl === $this->requestUri;
        } elseif ($this->matchType === 'begins_with') {
            return strpos($requestUrl, $this->requestUri) === 0;
        }

        return false;
    }
}
