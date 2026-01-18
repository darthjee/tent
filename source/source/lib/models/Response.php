<?php

namespace Tent\Models;

/**
 * Represents an HTTP response returned by a RequestHandler or the application.
 *
 * Contains the response body, HTTP status code, and header lines.
 */
class Response
{
    /**
     * @var string Response body content
     */
    private string $body;

    /**
     * @var int HTTP status code (e.g., 200, 404)
     */
    private int $httpCode;

    /**
     * @var array List of HTTP header lines (e.g., ['Content-Type: text/html'])
     */
    private array $headerLines;

    /**
     * Constructs a Response object.
     *
     * @param string  $body        The response body content.
     * @param integer $httpCode    The HTTP status code.
     * @param array   $headerLines List of HTTP header lines.
     */
    public function __construct(string $body, int $httpCode, array $headerLines)
    {
        $this->body = $body;
        $this->httpCode = $httpCode;
        $this->headerLines = $headerLines;
    }

    /**
     * Returns the response body content.
     *
     * @return string
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Returns the HTTP status code.
     *
     * @return integer
     */
    public function httpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * Returns the list of HTTP header lines.
     *
     * @return array
     */
    public function headerLines(): array
    {
        return $this->headerLines;
    }
}
