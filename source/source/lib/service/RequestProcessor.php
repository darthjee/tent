<?php

namespace Tent;

class RequestProcessor
{
    private $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public static function handleRequest($request)
    {
        return (new RequestProcessor($request))->handle();
    }

    public function handle()
    {
        // Check if request should be proxied to frontend
        if ($this->matchesFrontendRoute()) {
            // Proxy to frontend
            $handler = new ProxyRequestHandler('http://frontend:8080');
        } else {
            $handler = new MissingRequestHandler();
        }

        return $handler->handleRequest($this->request);
    }

    private function matchesFrontendRoute()
    {
        $matchers = [
            new RequestMatcher('GET', '/', 'exact')
        ];

        foreach ($matchers as $matcher) {
            if ($matcher->matches($this->request)) {
                return true;
            }
        }

        return false;
    }
}
