<?php

namespace ApiDev;

class Request
{
    /**
     * Represents an HTTP request.
     *
     * Provides methods to access the request method, body, headers, URL, and query parameters.
     */
    public function requestMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Retrieves the raw body of the HTTP request.
     */
    public function body()
    {
        return file_get_contents('php://input');
    }

    /**
     * Retrieves all HTTP request headers.
     *
     * @return array An associative array of all HTTP request headers.
     */
    public function headers(): array
    {
        return getallheaders();
    }

    /**
     * Retrieves the URL path of the HTTP request.
     *
     * @return string The URL path of the request.
     */
    public function requestUrl(): string
    {
        $uri = $_SERVER['REQUEST_URI'];
        $parts = parse_url($uri);
        return $parts['path'] ?? '/';
    }

    /**
     * Retrieves the query string from the HTTP request URL.
     *
     * @return string The query string of the request, or an empty string if none exists.
     */
    public function query(): string
    {
        $uri = $_SERVER['REQUEST_URI'];
        $parts = parse_url($uri);
        return $parts['query'] ?? '';
    }
}
