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
    private $requestPath;
    private $query;

    /**
     * List of attributes that can be set via constructor params.
     */
    private const ATTRIBUTES = [
        'request',
        'requestMethod',
        'body',
        'headers',
        'requestPath',
        'query',
    ];

    /**
     * ProcessingRequest constructor.
     *
     * Initializes the instance with optional parameters for each attribute.
     *
     * Example usage:
     * <code>
     * $processingRequest = new ProcessingRequest([
     *     'request' => $request, // Instance of Request
     *     'requestMethod' => 'POST',
     *     'body' => '{"foo":"bar"}',
     *     'headers' => ['X-Test' => 'ok'],
     *     'requestPath' => '/api/test',
     *     'query' => 'a=1&b=2',
     * ]);
     * </code>
     *
     * Any omitted parameter will be initialized as null and may be filled
     * on demand via delegation to the Request object.
     *
     * @param array $params Optional parameters to initialize the processed
     *   request attributes.
     */
    public function __construct(array $params = [])
    {
        foreach (self::ATTRIBUTES as $attr) {
            if (array_key_exists($attr, $params)) {
                $this->$attr = $params[$attr];
            }
        }
    }

    /**
     * Sets a header value in the cached headers array.
     *
     * @param string $name  The header name.
     * @param string $value The header value.
     *
     * @return string The set header value.
     */
    public function setHeader(string $name, string $value)
    {
        $this->headers();

        return $this->headers[$name] = $value;
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

    function setRequestPath(string $uri): string
    {
        return $this->requestPath = $uri;
    }

    /**
     * Returns the request URL path (e.g., /index.html), caching the result after first access.
     *
     * @return string|null The path portion of the request URL or null if no request is set
     *
     * @see RequestInterface::requestPath()
     */
    public function requestPath()
    {
        if ($this->requestPath === null && $this->request) {
            $this->requestPath = $this->request->requestPath();
        }
        return $this->requestPath;
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
