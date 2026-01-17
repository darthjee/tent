<?php

namespace Tent\Models;

/**
 * Represents an HTTP request, extracting values from $_SERVER or provided options.
 *
 * For production, values are extracted from PHP's $_SERVER and related globals. For testing,
 * you can initialize with an array of options to override any value (method, body, headers, etc).
 */
class Request implements RequestInterface
{
    private $options;

    /**
     * @param array $options Optional overrides for request properties (used in tests).
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * Returns the HTTP request method (e.g., GET, POST).
     *
     * @return string
     */
    public function requestMethod()
    {
        if (isset($this->options['requestMethod'])) {
            return $this->options['requestMethod'];
        }
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Returns the request body.
     *
     * @return string
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
     * @return array
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
     * @return string
     */
    public function requestUrl()
    {
        if (isset($this->options['requestUrl'])) {
            return $this->options['requestUrl'];
        }
        $uri = $_SERVER['REQUEST_URI'];
        $parts = parse_url($uri);
        return $parts['path'] ?? '/';
    }

    /**
     * Returns the query string from the request URL.
     *
     * @return string
     */
    public function query()
    {
        if (isset($this->options['query'])) {
            return $this->options['query'];
        }
        $uri = $_SERVER['REQUEST_URI'];
        $parts = parse_url($uri);
        return $parts['query'] ?? '';
    }
}
