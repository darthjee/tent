<?php

namespace ApiDev;

class MockRequest implements RequestInterface
{
    private const ATTRIBUTES_DEFAULT = [
        'requestMethod' => 'GET',
        'body' => '',
        'headers' => [],
        'requestUrl' => '/',
        'query' => ''
    ];

    private string $requestMethod;
    private $body;
    private array $headers;
    private string $requestUrl;
    private string $query;

    /**
     * Creates a mock request with configurable attributes.
     *
     * @param array $attributes Associative array with keys:
     *                          - 'requestMethod' (string, default: 'GET')
     *                          - 'body' (mixed, default: '')
     *                          - 'headers' (array, default: [])
     *                          - 'requestUrl' (string, default: '/')
     *                          - 'query' (string, default: '')
     */
    public function __construct(array $attributes = [])
    {
        foreach (self::ATTRIBUTES_DEFAULT as $attribute => $default) {
            $this->$attribute = $attributes[$attribute] ?? $default;
        }
    }

    public function requestMethod(): string
    {
        return $this->requestMethod;
    }

    public function body()
    {
        return $this->body;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function requestUrl(): string
    {
        return $this->requestUrl;
    }

    public function query(): string
    {
        return $this->query;
    }
}
