<?php

namespace Tent\Service;

use Tent\RequestHandlers\MissingRequestHandler;
use Tent\RequestHandlers\RequestHandler;
use Tent\Configuration;
use Tent\Models\Request;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;

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
     * @var ProcessingRequest The incoming HTTP request to be processed.
     */
    private ProcessingRequest $request;

    /**
     * Constructs a RequestProcessor.
     *
     * @param Request $request The incoming HTTP request.
     */
    public function __construct(Request $request)
    {
        $this->request = new ProcessingRequest(['request' => $request]);
    }

    /**
     * Static entry point to process a request.
     *
     * @param Request $request The incoming HTTP request.
     * @return Response The processed response.
     */
    public static function handleRequest(Request $request): Response
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
    public function handle(): Response
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
    private function getRequestHandler(): RequestHandler
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
