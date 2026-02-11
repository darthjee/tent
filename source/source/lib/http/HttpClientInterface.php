<?php

namespace Tent\Http;

/**
 * Interface for HTTP client implementations used in proxying requests.
 *
 * This interface allows for abstraction of HTTP clients, making it easier to swap implementations
 * (such as CurlHttpClient or mocks/stubs for testing). It is especially useful for unit testing
 * proxy handlers, as you can inject a mock client and control the responses.
 */
interface HttpClientInterface
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
    public function request(string $method, string $url, array $headers, ?string $body = null): array;

    /**
     * Sends an HTTP GET request to the given URL with the provided headers.
     *
     * @param string $url     The target URL for the request.
     * @param array  $headers Associative array of headers to send.
     * @return array Response data (format depends on implementation).
     */
    public function get(string $url, array $headers): array;

    /**
     * Sends an HTTP POST request to the given URL with the provided headers and body.
     *
     * @param string $url     The target URL for the request.
     * @param array  $headers Associative array of headers to send.
     * @param string $body    The request body/payload to send.
     * @return array Response data (format depends on implementation).
     */
    public function post(string $url, array $headers, string $body): array;
}
