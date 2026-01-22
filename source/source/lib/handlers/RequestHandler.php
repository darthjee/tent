<?php

namespace Tent\Handlers;

use Tent\Models\RequestInterface;
use Tent\Middlewares\Middleware;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;

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
     * @param RequestInterface $request The incoming request to process.
     * @return Response The response to be sent back.
     */
    abstract protected function processsRequest(RequestInterface $request): Response;

    /**
     * @var array Middlewares to be applied to this handler
     */
    protected $middlewares = [];

    /**
     * Handles an incoming Request and returns a Response.
     *
     * @param ProcessingRequest $request The incoming HTTP request.
     * @return Response The response to be sent back.
     */
    final public function handleRequest(ProcessingRequest $request): Response
    {
        $request = $this->applyRequestMiddlewares($request);

        if ($request->hasResponse()) {
            $response = $request->response();
        } else {
            $response = $this->processsRequest($request);
        }

        return $this->applyResponseMiddlewares($response);
    }

    /**
     * Adds a middleware to the list of middlewares.
     *
     * @param Middleware $middleware The middleware to
     *   add which will be applied in a request.
     * @return Middleware The added middleware.
     */
    public function addMiddleware(Middleware $middleware): Middleware
    {
        return $this->middlewares[] = $middleware;
    }

    /**
     * Builds and adds a middleware to the list of middlewares.
     *
     * @param array $middlewareAttributes Associative array with keys for Middleware::build.
     * @return Middleware The added middleware.
     */
    public function buildMiddleware(array $middlewareAttributes): Middleware
    {
        return $this->addMiddleware(Middleware::build($middlewareAttributes));
    }

    /**
     * Builds and adds multiple middlewares to the handler.
     *
     * @param array $attributes Array of associative arrays,
     *   each with keys for Middleware::build.
     * @return array The list of middlewares.
     */
    public function buildMiddlewares(array $attributes): array
    {
        foreach ($attributes as $attributes) {
            $this->addMiddleware(Middleware::build($attributes));
        }
        return $this->middlewares;
    }

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
        if (!isset($params['type']) && !isset($params['class'])) {
            throw new \InvalidArgumentException('Missing handler type');
        }

        return self::handlerClass($params)::build($params);
    }

    /**
     * Determines the handler class based on parameters.
     *
     * @param array $params Associative array with keys 'type' or 'class'.
     * @return string The fully qualified class name of the handler.
     * @throws \InvalidArgumentException If type is unknown.
     */
    protected static function handlerClass(array $params): string
    {
        if ($params['class'] ?? false) {
            return $params['class'];
        }

        switch ($params['type']) {
            case 'proxy':
                return \Tent\Handlers\ProxyRequestHandler::class;
            case 'fixed':
                return \Tent\Handlers\FixedFileHandler::class;
            case 'static':
                return \Tent\Handlers\StaticFileHandler::class;
            default:
                throw new \InvalidArgumentException('Unknown handler type: ' . $params['type']);
        }
    }

    /**
     * Applies all middlewares to the given request.
     *
     * @param ProcessingRequest $request The incoming HTTP request.
     * @return ProcessingRequest The modified request after applying all middlewares.
     *
     * @see Middleware::processRequest
     */
    private function applyRequestMiddlewares(ProcessingRequest $request): ProcessingRequest
    {
        $modifiedRequest = $request;
        foreach ($this->middlewares as $middleware) {
            if ($modifiedRequest->hasResponse()) {
                return $modifiedRequest;
            }
            $modifiedRequest = $middleware->processRequest($modifiedRequest);
        }
        return $modifiedRequest;
    }

    /**
     * Applies all middlewares to the given response.
     *
     * @param Response $response The response to be sent back.
     * @return Response The modified response after applying all middlewares.
     *
     * @see Middleware::processResponse
     */
    private function applyResponseMiddlewares(Response $response): Response
    {
        $modifiedResponse = $response;
        foreach ($this->middlewares as $middleware) {
            $modifiedResponse = $middleware->processResponse($modifiedResponse);
        }
        return $modifiedResponse;
    }
}
