<?php

namespace Tent\Models;

use Tent\Common\SimpleModel;

/**
 * Represents an HTTP response returned by a RequestHandler or the application.
 *
 * Contains the response body, HTTP status code, and header lines.
 *
 * Usage:
 *   $response = new Response([
 *     'body' => 'some body',
 *     'httpCode' => 200,
 *     'headerLines' => ['Content-Type: text/html']
 *   ]);
 */
class Response extends SimpleModel
{
    /**
     * Default values for response attributes.
     */
    protected const DEFAULT_ATTRIBUTES = [
        'body' => '',
        'httpCode' => 200,
        'headers' => [],
        'request' => null
    ];

    /**
     * @var string Response body content
     */
    protected string $body;

    /**
     * @var int HTTP status code (e.g., 200, 404)
     */
    protected int $httpCode;

    /**
     * @var array List of HTTP header lines (e.g., ['Content-Type: text/html'])
     */
    protected array $headers;

    /**
     * @var RequestInterface The original request associated with this response (optional).
     */
    protected RequestInterface $request;

    /**
     * Processes an attribute key and value before assignment.
     *
     * Instantiates a default Request object when request value is null.
     *
     * @param string $key   The attribute key from DEFAULT_ATTRIBUTES.
     * @param mixed  $value The value to be assigned (may be from data or default).
     * @return array An array with two elements: [attribute_name, processed_value].
     */
    protected function processAttributeValue(string $key, $value): array
    {
        // Special handling for request: instantiate default Request if null
        if ($key === 'request' && $value === null) {
            $value = new Request();
        }

        return [$key, $value];
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
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Returns the original request associated with this response, if any.
     *
     * @return RequestInterface
     */
    public function request()
    {
        return $this->request;
    }

    /**
     * Sets the response body content.
     *
     * @param string $body The new resposne body content.
     * @return string
     */
    public function setBody(string $body): string
    {
        return $this->body = $body;
    }

    /**
     * Sets the HTTP status code.
     *
     * @param integer $httpCode The new HTTP status code.
     * @return integer
     */
    public function setHttpCode(int $httpCode): int
    {
        return $this->httpCode = $httpCode;
    }

    /**
     * Sets the list of HTTP header lines.
     *
     * @param array $headers The new list of HTTP header lines.
     * @return array
     */
    public function setHeaders(array $headers): array
    {
        return $this->headers = $headers;
    }
}
