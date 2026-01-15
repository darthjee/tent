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
    public $body;

    /**
     * @var int HTTP status code (e.g., 200, 404)
     */
    public $httpCode;

    /**
     * @var array List of HTTP header lines (e.g., ['Content-Type: text/html'])
     */
    public $headerLines;

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
}
