<?php

namespace Tent;

use Tent\HttpClientInterface;

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
     * @param string $url The target URL for the request.
     * @param array $headers The headers to send with the request.
     * @return array An array with 'body', 'httpCode', and 'headers' from the response.
     */
    public function request($url, $headers)
    {
        $headerLines = CurlUtils::buildHeaderLines($headers);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);

        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        curl_close($ch);

        $headerLines = CurlUtils::parseResponseHeaders($headers);

        return [
            'body' => $body,
            'httpCode' => $httpCode,
            'headers' => $headerLines
        ];
    }
}
