<?php

namespace Tent\Models;

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
    private array $headers;

    /**
     * @var RequestInterface The original request associated with this response (optional).
     */
    private RequestInterface $request;

    /**
     * Constructs a Response object.
     *
     * @param array $data Associative array with possible keys:
     *   - body: string (response body content)
     *   - httpCode: int (HTTP status code)
     *   - headers: array (list of HTTP header lines)
     *   - request: RequestInterface (the original request associated with this response).
     */
    public function __construct(array $data)
    {
        $this->body = $data['body'] ?? '';
        $this->httpCode = $data['httpCode'] ?? 200;
        $this->headers = $data['headers'] ?? [];
        $this->request = $data['request'] ?? new Request();
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
     * @param array $header The new list of HTTP header lines.
     * @return array
     */
    public function setHeaders(array $headers): array
    {
        return $this->headers = $headers;
    }
}
