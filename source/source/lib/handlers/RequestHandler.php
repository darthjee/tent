<?php

namespace Tent\Handlers;

use Tent\Models\Request;

/**
 * Abstract class for handling HTTP requests and producing responses.
 *
 * Implementations of this class are responsible for processing incoming
 * HTTP requests and returning appropriate responses. Typical implementations
 * include proxying requests to other servers or serving static files. Future
 * implementations may include caching mechanisms.
 */
abstract class RequestHandler
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
    abstract public function handleRequest(Request $request);

    /**
     * Factory method to build a RequestHandler based on type and parameters.
     *
     * Example:
     *   RequestHandler::build(['type' => 'proxy', 'host' => 'http://api.com'])
     *   RequestHandler::build(['type' => 'fixed', 'file' => './some/path/file.txt'])
     *   RequestHandler::build(['type' => 'static', 'location' => './some_folder'])
     *
     * @param array $params Associative array with at least the key 'type'.
     * @return RequestHandler
     * @throws \InvalidArgumentException If type is missing or unknown.
     */
    public static function build(array $params): self
    {
        if (!isset($params['type'])) {
            throw new \InvalidArgumentException('Missing handler type');
        }
        
        switch ($params['type']) {
            case 'proxy':
                return \Tent\Handlers\ProxyRequestHandler::build($params);
            case 'fixed':
                return \Tent\Handlers\FixedFileHandler::build($params);
            case 'static':
                return \Tent\Handlers\StaticFileHandler::build($params);
            default:
                throw new \InvalidArgumentException('Unknown handler type: ' . $params['type']);
        }
    }
}
