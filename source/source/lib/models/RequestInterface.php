<?php

namespace Tent\Models;

interface RequestInterface
{
    /**
     * Returns the HTTP request method (e.g., GET, POST).
     * @return string
     */
    public function requestMethod();

    /**
     * Returns the request body.
     * @return string
     */
    public function body();

    /**
     * Returns the request headers as an associative array.
     * @return array
     */
    public function headers();

    /**
     * Returns the request URL path (e.g., /index.html).
     * @return string
     */
    public function requestUrl();

    /**
     * Returns the query string from the request URL.
     * @return string
     */
    public function query();
}
