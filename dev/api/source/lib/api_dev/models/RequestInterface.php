<?php

namespace ApiDev;

interface RequestInterface
{
    /**
     * Retrieves the HTTP request method (e.g., GET, POST, PUT, DELETE).
     *
     * @return string The HTTP request method.
     */
    public function requestMethod(): string;

    /**
     * Retrieves the raw body of the HTTP request.
     *
     * @return mixed The request body content.
     */
    public function body();

    /**
     * Retrieves all HTTP request headers.
     *
     * @return array An associative array of all HTTP request headers.
     */
    public function headers(): array;

    /**
     * Retrieves the URL path of the HTTP request.
     *
     * @return string The URL path of the request.
     */
    public function requestUrl(): string;

    /**
     * Retrieves the query string from the HTTP request URL.
     *
     * @return string The query string of the request, or an empty string if none exists.
     */
    public function query(): string;
}
