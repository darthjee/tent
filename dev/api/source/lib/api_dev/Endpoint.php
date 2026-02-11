<?php

namespace ApiDev;

/**
 * Abstract base class for API endpoints.
 *
 * Represents a handler for HTTP requests. Each endpoint receives a request
 * and processes it to return an appropriate Response.
 */
abstract class Endpoint
{
    /**
     * @var RequestInterface The HTTP request being handled
     */
    protected $request;

    /**
     * Creates a new Endpoint instance.
     *
     * @param RequestInterface $request The HTTP request to handle
     */
    public function __construct(RequestInterface $request)
    {
        $this->request = $request;
    }

    /**
     * Handles the request and returns a Response.
     *
     * @return Response The HTTP response
     */
    abstract public function handle(): Response;
}
