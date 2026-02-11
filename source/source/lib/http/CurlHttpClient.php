<?php

namespace Tent\Http;

use Tent\Utils\CurlUtils;
use Tent\Http\CurlHttpExecutor;

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
     * Sends an HTTP GET request to the given URL with the provided headers.
     *
     * This method performs a GET request using cURL. It accepts a URL and an associative array of headers.
     * The response is returned as an array containing:
     *   - 'body': The response body as a string
     *   - 'httpCode': The HTTP status code (e.g., 200, 404)
     *   - 'headers': An array of response headers in "Key: Value" format
     *
     * Usage example:
     *   $client = new CurlHttpClient();
     *   $result = $client->get('http://httpbin/get', ['User-Agent' => 'PHPUnit-Test']);
     *   // $result['body'] contains the response body
     *   // $result['httpCode'] contains the status code
     *   // $result['headers'] contains the response headers
     *
     * @param string $url     The target URL for the GET request (may include query parameters).
     * @param array  $headers Associative array of headers to send (e.g., ['User-Agent' => 'Test']).
     * @return array{
     *   body: string,
     *   httpCode: int,
     *   headers: string[]
     * } Array with response body, status code, and headers.
     */
    public function get(string $url, array $headers)
    {
        return new CurlHttpExecutor($url, $headers)->get();
    }

    /**
     * Sends an HTTP POST request to the given URL with the provided headers and body.
     *
     * This method performs a POST request using cURL. It accepts a URL, an associative array of headers,
     * and a request body (payload). The response is returned as an array containing:
     *   - 'body': The response body as a string
     *   - 'httpCode': The HTTP status code (e.g., 200, 201, 404)
     *   - 'headers': An array of response headers in "Key: Value" format
     *
     * Usage example:
     *   $client = new CurlHttpClient();
     *   $result = $client->post('http://api/users', ['Content-Type' => 'application/json'], '{"name":"John"}');
     *   // $result['body'] contains the response body
     *   // $result['httpCode'] contains the status code
     *   // $result['headers'] contains the response headers
     *
     * @param string $url     The target URL for the POST request.
     * @param array  $headers Associative array of headers to send (e.g., ['Content-Type' => 'application/json']).
     * @param string $body    The request body/payload to send.
     * @return array{
     *   body: string,
     *   httpCode: int,
     *   headers: string[]
     * } Array with response body, status code, and headers.
     */
    public function post(string $url, array $headers, string $body)
    {
        return new CurlHttpExecutor($url, $headers)->post($body);
    }
}
