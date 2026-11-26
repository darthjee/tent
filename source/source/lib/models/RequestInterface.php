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
    public function requestPath(): string;

    /**
     * Returns the query string from the request URL.
     * @return string
     */
    public function query();

    /**
     * Returns uploaded files in $_FILES format.
     * @return array
     */
    public function uploadedFiles(): array;

    /**
     * Returns POST form fields (mirrors $_POST).
     * @return array
     */
    public function postFields(): array;

    /**
     * Returns the request's Host header (e.g., mydomain.com or mydomain.com:8080).
     * @return string
     */
    public function domain(): string;

    /**
     * Marks this request so that the cache middlewares must not read, write or evict.
     *
     * The flag is proxy-internal and one-way: it can only be set, never cleared, and it
     * is never serialized into a header. It is honoured by FileCacheMiddleware and
     * CacheStalenessMiddleware.
     *
     * @return void
     */
    public function skipCache(): void;

    /**
     * Tells whether the cache was skipped for this request (see skipCache()).
     * @return bool
     */
    public function cacheSkipped(): bool;
}
