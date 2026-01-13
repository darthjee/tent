<?php

namespace Tent;

class Request
{
    private $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function requestMethod()
    {
        if (isset($this->options['requestMethod'])) {
            return $this->options['requestMethod'];
        }
        return $_SERVER['REQUEST_METHOD'];
    }

    public function body()
    {
        if (isset($this->options['body'])) {
            return $this->options['body'];
        }
        return file_get_contents('php://input');
    }

    public function headers()
    {
        if (isset($this->options['headers'])) {
            return $this->options['headers'];
        }
        return getallheaders();
    }

    public function requestUrl()
    {
        if (isset($this->options['requestUrl'])) {
            return $this->options['requestUrl'];
        }
        $uri = $_SERVER['REQUEST_URI'];
        $parts = parse_url($uri);
        return $parts['path'] ?? '/';
    }

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
