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
     * Sends an HTTP GET request to the given URL with the provided headers.
     *
     * @param string $url     The target URL for the request.
     * @param array  $headers Associative array of headers to send.
     * @return array Response data (format depends on implementation).
     */
    public function get(string $url, array $headers);

    /**
     * Sends an HTTP POST request to the given URL with the provided headers and body.
     *
     * @param string $url     The target URL for the request.
     * @param array  $headers Associative array of headers to send.
     * @param string $body    The request body/payload to send.
     * @return array Response data (format depends on implementation).
     */
    public function post(string $url, array $headers, string $body);
}
