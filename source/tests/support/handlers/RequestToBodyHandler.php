<?php

namespace Tent\Tests\Support\Handlers;

use Tent\RequestHandlers\RequestHandler;
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
    protected function processsRequest(RequestInterface $request): Response
    {
        $body = json_encode([
            'uri' => $request->requestPath(),
            'query' => $request->query(),
            'method' => $request->requestMethod(),
            'headers' => $request->headers(),
            'body' => $request->body(),
        ]);
        return new Response([
            'body' => $body,
            'httpCode' => 200,
            'headers' => ['Content-Type: application/json'],
            'request' => $request
        ]);
    }

    public static function build(array $params): self
    {
        return new self();
    }
}
