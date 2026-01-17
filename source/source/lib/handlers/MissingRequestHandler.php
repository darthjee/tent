<?php

namespace Tent\Handlers;

use Tent\Handlers\RequestHandler;
use Tent\Models\RequestInterface;
use Tent\Models\MissingResponse;

/**
 * RequestHandler that always returns a 404 response.
 *
 * This handler is used when no other handler matches the request. It always returns
 * a MissingResponse, representing a 404 Not Found.
 */
class MissingRequestHandler extends RequestHandler
{
    /**
     * Returns a MissingResponse (404 Not Found) for any request.
     *
     * @param Request $request The incoming HTTP request.
     * @return MissingResponse The 404 response.
     */
    public function handleRequest(RequestInterface $request)
    {
        return new MissingResponse();
    }
}
