<?php

namespace Tent\Tests\Support\Handlers;

use Tent\Handlers\RequestHandler;
use Tent\Models\RequestInterface;
use Tent\Models\Response;

/**
 * Test handler that will be used only for testing purposes.
 * The processRequest method should be implemented as needed in tests.
 */
class RequestToBodyHandler extends RequestHandler
{
    /**
     * Implement this method in your test to define the handler's behavior.
     *
     * @param RequestInterface $request
     * @return Response
     */
    public function processRequest(RequestInterface $request)
    {
        $body = json_encode([
            'uri' => $request->requestUrl(),
            'query' => $request->query(),
            'method' => $request->requestMethod(),
            'headers' => $request->headers(),
            'body' => $request->body(),
        ]);
        return new Response(
            $body, 200, ['Content-Type: application/json']
        );
    }
}
