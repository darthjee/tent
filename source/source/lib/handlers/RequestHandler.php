<?php

namespace Tent\Handlers;

use Tent\Models\Request;

/**
 * Interface for handling HTTP requests and producing responses.
 *
 * Implementations of this interface are responsible for processing incoming
 * HTTP requests and returning appropriate responses. Typical implementations
 * include proxying requests to other servers or serving static files. Future
 * implementations may include caching mechanisms.
 */
interface RequestHandler
{
    /**
     * Processes an incoming Request and returns a Response.
     *
     * The request object represents the received HTTP request. The implementation
     * should process the request and return a Response. Processing may involve
     * proxying the request or serving a static file, depending on the child class.
     *
     * Future implementations may include a CachedProxyRequestHandler, which can
     * serve responses from cache or proxy as needed.
     *
     * @param Request $request The incoming request to process.
     * @return Response The response to be sent back.
     */
    public function handleRequest(Request $request);
}
