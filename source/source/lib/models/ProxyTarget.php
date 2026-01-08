<?php

namespace Tent;

class ProxyTarget
{
    private $handler;
    private $matchers;

    public function __construct($handler, $matchers = [])
    {
        $this->handler = $handler;
        $this->matchers = $matchers;
    }

    public function handler()
    {
        return $this->handler;
    }

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
