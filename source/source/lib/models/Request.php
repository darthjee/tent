<?php

namespace Tent\Models;

/**
 * Class Request
 *
 * Represents an HTTP request, extracting values from $_SERVER or provided options.
 *
 * This class implements {@see RequestInterface} and provides access to HTTP request
 * data such as method, body, headers, URL, and query string.
 *
 * For production, values are extracted from PHP's $_SERVER and related globals. For testing,
 * you can initialize with an array of options to override any value (method, body, headers, etc).
 *
 * @implements RequestInterface
 */
class Request implements RequestInterface
{
    private $options;

    /**
     * Request constructor.
     *
     * @param array $options Optional overrides for request properties (used in tests).
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * Returns the HTTP request method (e.g., GET, POST).
     *
     * @return string HTTP method (e.g., GET, POST, PUT, DELETE)
     *
     * @see RequestInterface::requestMethod()
     */
    public function requestMethod()
    {
        if (isset($this->options['requestMethod'])) {
            return $this->options['requestMethod'];
        }
        return $this->get('request_method');
    }

    /**
     * Returns the request body.
     *
     * @return string The raw request body as a string
     *
     * @see RequestInterface::body()
     */
    public function body()
    {
        if (isset($this->options['body'])) {
            return $this->options['body'];
        }
        return file_get_contents('php://input');
    }

    /**
     * Returns the request headers as an associative array.
     *
     * @return array Associative array of request headers
     *
     * @see RequestInterface::headers()
     */
    public function headers()
    {
        if (isset($this->options['headers'])) {
            return $this->options['headers'];
        }
        return getallheaders();
    }

    /**
     * Returns the request URL path (e.g., /index.html).
     *
     * @return string The path portion of the request URL
     *
     * @see RequestInterface::requestPath()
     */
    public function requestPath()
    {
        if (isset($this->options['requestPath'])) {
            return $this->options['requestPath'];
        }
        $uri = $this->get('uri');
        $parts = parse_url($uri);
        return $parts['path'] ?? '/';
    }

    /**
     * Returns the query string from the request URL.
     *
     * @return string The query string, or an empty string if none is present
     *
     * @see RequestInterface::query()
     */
    public function query()
    {
        if (isset($this->options['query'])) {
            return $this->options['query'];
        }
        $uri = $this->get('uri');
        $parts = parse_url($uri);
        return $parts['query'] ?? '';
    }

    /**
     * Helper method to get values from $_SERVER superglobal.
     * @param string $information The key to retrieve (e.g., 'uri', 'request_method').
     * @return mixed The value from $_SERVER or null if not set.
     */
    private function get(string $information): mixed
    {
        switch ($information) {
            case 'uri':
                $key = 'REQUEST_URI';
                break;
            case 'request_method':
                $key = 'REQUEST_METHOD';
                break;
            default:
                throw new InvalidArgumentException('Invalid attribute access: ' . $information);
        }
        return $_SERVER[$key] ?? null;
    }
}
