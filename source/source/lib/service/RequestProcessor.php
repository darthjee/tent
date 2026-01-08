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
        $targets = $this->getTargets();

        foreach ($targets as $target) {
            if ($target->match($this->request)) {
                return $target->handler();
            }
        }
    }

    private function getTargets()
    {
        return [
            new ProxyTarget(
                new ProxyRequestHandler(new Server('http://frontend:8080')),
                [
                ]
            ),
            new ProxyTarget(new MissingRequestHandler())
        ];
    }
}
