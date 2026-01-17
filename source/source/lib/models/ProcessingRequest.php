<?php

namespace Tent\Models;

/**
 * ProcessingRequest wraps a Request and lazily initializes its properties.
 *
 * Usage:
 *   $pr = new ProcessingRequest(['request' => $request]);
 *   $pr->requestMethod();
 *   $pr->body();
 *   ...
 */
class ProcessingRequest
{
    /**
     * @var Request
     */
    private $request;

    private $requestMethod;
    private $body;
    private $headers;
    private $requestUrl;
    private $query;

    public function __construct(array $params = [])
    {
        $this->request = $params['request'] ?? null;
    }

    public function requestMethod()
    {
        if ($this->requestMethod === null && $this->request) {
            $this->requestMethod = $this->request->requestMethod();
        }
        return $this->requestMethod;
    }

    public function body()
    {
        if ($this->body === null && $this->request) {
            $this->body = $this->request->body();
        }
        return $this->body;
    }

    public function headers()
    {
        if ($this->headers === null && $this->request) {
            $this->headers = $this->request->headers();
        }
        return $this->headers;
    }

    public function requestUrl()
    {
        if ($this->requestUrl === null && $this->request) {
            $this->requestUrl = $this->request->requestUrl();
        }
        return $this->requestUrl;
    }

    public function query()
    {
        if ($this->query === null && $this->request) {
            $this->query = $this->request->query();
        }
        return $this->query;
    }
}
