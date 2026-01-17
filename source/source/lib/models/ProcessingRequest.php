<?php

namespace Tent\Models;

/**
 * Class ProcessingRequest
 *
 * Wraps a {@see Request} and lazily initializes its properties for efficient repeated access.
 *
 * Implements {@see RequestInterface} and delegates all method calls to the underlying Request instance,
 * caching the results for performance. Useful for scenarios where request data may be accessed multiple times
 * during processing, avoiding redundant computation or I/O.
 *
 * Usage example:
 *   $pr = new ProcessingRequest(['request' => $request]);
 *   $pr->requestMethod();
 *   $pr->body();
 *   ...
 *
 * @implements RequestInterface
 */
class ProcessingRequest implements RequestInterface
{
    /**
     * The underlying Request instance to delegate to.
     *
     * @var Request|null
     */
    private $request;

    private $requestMethod;
    private $body;
    private $headers;
    private $requestUrl;
    private $query;

    /**
     * ProcessingRequest constructor.
     *
     * @param array $params Must include 'request' => Request instance to wrap.
     */
    public function __construct(array $params = [])
    {
        $this->request = $params['request'] ?? null;
    }

    /**
     * Returns the HTTP request method (e.g., GET, POST), caching the result after first access.
     *
     * @return string|null HTTP method or null if no request is set
     *
     * @see RequestInterface::requestMethod()
     */
    public function requestMethod()
    {
        if ($this->requestMethod === null && $this->request) {
            $this->requestMethod = $this->request->requestMethod();
        }
        return $this->requestMethod;
    }

    /**
     * Returns the request body, caching the result after first access.
     *
     * @return string|null The raw request body or null if no request is set
     *
     * @see RequestInterface::body()
     */
    public function body()
    {
        if ($this->body === null && $this->request) {
            $this->body = $this->request->body();
        }
        return $this->body;
    }

    /**
     * Returns the request headers as an associative array, caching the result after first access.
     *
     * @return array|null Associative array of request headers or null if no request is set
     *
     * @see RequestInterface::headers()
     */
    public function headers()
    {
        if ($this->headers === null && $this->request) {
            $this->headers = $this->request->headers();
        }
        return $this->headers;
    }

    /**
     * Returns the request URL path (e.g., /index.html), caching the result after first access.
     *
     * @return string|null The path portion of the request URL or null if no request is set
     *
     * @see RequestInterface::requestUrl()
     */
    public function requestUrl()
    {
        if ($this->requestUrl === null && $this->request) {
            $this->requestUrl = $this->request->requestUrl();
        }
        return $this->requestUrl;
    }

    /**
     * Returns the query string from the request URL, caching the result after first access.
     *
     * @return string|null The query string or null if no request is set
     *
     * @see RequestInterface::query()
     */
    public function query()
    {
        if ($this->query === null && $this->request) {
            $this->query = $this->request->query();
        }
        return $this->query;
    }
}
