<?php

namespace ApiDev;

/**
 * Represents an HTTP response.
 * 
 * Encapsulates the response body, HTTP status code, and headers to be sent to the client.
 */
class Response
{
    /**
     * @var string The response body content
     */
    public $body;
    
    /**
     * @var int The HTTP status code
     */
    public $httpCode;
    
    /**
     * @var array The response headers as strings
     */
    public $headerLines;

    /**
     * Creates a new Response instance.
     * 
     * @param string $body The response body content
     * @param int $httpCode The HTTP status code (e.g., 200, 404, 500)
     * @param array $headerLines Array of header strings (e.g., ['Content-Type: application/json'])
     */
    public function __construct(string $body, int $httpCode, array $headerLines = [])
    {
        $this->body = $body;
        $this->httpCode = $httpCode;
        $this->headerLines = $headerLines;
    }

    /**
     * Returns the HTTP status code.
     * 
     * @return int The HTTP status code
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * Returns the response headers.
     * 
     * @return array The response headers
     */
    public function getHeaders(): array
    {
        return $this->headerLines;
    }

    /**
     * Returns the response body.
     * 
     * @return string The response body content
     */
    public function getBody(): string
    {
        return $this->body;
    }
}
