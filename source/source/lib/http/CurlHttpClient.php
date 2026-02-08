<?php

namespace Tent\Http;

use Tent\Utils\CurlUtils;

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
     *   $result = $client->request('http://httpbin/get', ['User-Agent' => 'PHPUnit-Test']);
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
    public function request(string $url, array $headers)
    {
        $headerLines = CurlUtils::buildHeaderLines($headers);


        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headerLines);

        $response = curl_exec($curl);
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        curl_close($curl);

        $headerLines = CurlUtils::parseResponseHeaders($headers);

        return [
            'body' => $body,
            'httpCode' => $httpCode,
            'headers' => $headerLines
        ];
    }
}
