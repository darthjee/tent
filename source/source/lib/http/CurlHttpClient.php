<?php

namespace Tent\Http;

use Tent\Http\CurlHttpExecutor\Get;
use Tent\Http\CurlHttpExecutor\Post;

/**
 * HTTP client for proxying requests using cURL.
 *
 * This class is responsible for making HTTP requests to the target server when acting as a proxy.
 * Currently, only GET requests are implemented. It uses cURL to perform the request and returns
 * the response body, HTTP status code, and headers as an array.
 */
class CurlHttpClient implements HttpClientInterface
{
    /**
     * Sends an HTTP request to the given URL with the provided method, headers, and optional body.
     *
     * This method serves as a general request handler that can be used for different HTTP methods.
     * It determines the appropriate executor class based on the method and executes the request.
     *
     * @param string      $method  The HTTP method (e.g., 'GET', 'POST').
     * @param string      $url     The target URL for the request (may include query parameters).
     * @param array       $headers Associative array of headers to send (e.g., ['User-Agent' => 'Test']).
     * @param string|null $body    Optional request body/payload to send (used for POST requests).
     * @return array{
     *   body: string,
     *   httpCode: int,
     *   headers: string[]
     * } Array with response body, status code, and headers.
     */
    public function request(string $method, string $url, array $headers, ?string $body = null): array
    {
        $executorClass = match (strtoupper($method)) {
            'GET' => Get::class,
            'POST' => Post::class,
            default => throw new InvalidArgumentException("Unsupported HTTP method: $method"),
        };

        $executor = new $executorClass(['url' => $url, 'headers' => $headers, 'body' => $body]);
        return $executor->request();
    }
}
