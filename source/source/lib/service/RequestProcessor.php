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
        $handler = $this->getRequestHandler();

        return $handler->handleRequest($this->request);
    }

    private function getRequestHandler()
    {
        $rules = Configuration::getRules();

        foreach ($rules as $rule) {
            if ($rule->match($this->request)) {
                return $rule->handler();
            }
        }
    }
}
