<?php

namespace Tent\Service;

use Tent\Handlers\MissingRequestHandler;
use Tent\Models\RequestInterface;
use Tent\Configuration;

/**
 * Main engine for processing incoming HTTP requests.
 *
 * RequestProcessor receives a Request, iterates through all Rules,
 *   and delegates the request to the appropriate handler.
 * If no handler is found, MissingRequestHandler is used to handle the request.
 */
class RequestProcessor
{
    /**
     * @var RequestInterface The incoming HTTP request to be processed.
     */
    private $request;

    /**
     * Constructs a RequestProcessor.
     *
     * @param RequestInterface $request The incoming HTTP request.
     */
    public function __construct(RequestInterface $request)
    {
        $this->request = $request;
    }

    /**
     * Static entry point to process a request.
     *
     * @param RequestInterface $request The incoming HTTP request.
     * @return Response The processed response.
     */
    public static function handleRequest(RequestInterface $request)
    {
        return (new RequestProcessor($request))->handle();
    }

    /**
     * Processes the request and returns the response.
     *
     * Finds the appropriate handler and delegates the request.
     *
     * @return Response
     */
    public function handle()
    {
        $handler = $this->getRequestHandler();

        return $handler->handleRequest($this->request);
    }

    /**
     * Finds the appropriate RequestHandler for the request.
     *
     * Iterates through all Rules and returns the handler for the first matching rule.
     * If no rule matches, returns MissingRequestHandler.
     *
     * @return RequestHandler|MissingRequestHandler
     */
    private function getRequestHandler()
    {
        $rules = Configuration::getRules();

        foreach ($rules as $rule) {
            if ($rule->match($this->request)) {
                return $rule->handler();
            }
        }

        return new MissingRequestHandler();
    }
}
