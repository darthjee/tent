<?php

namespace Tent\Http;

use Tent\Utils\CurlUtils;

class CurlHttpExecutor
{
    private string $url;
    private array $headers;
    private ?string $body;

    public function __construct(array $options)
    {
        $this->url = $options['url'] ?? '';
        $this->headers = $options['headers'] ?? [];
        $this->body = $options['body'] ?? null;
    }

    public function get()
    {
        $headerLines = CurlUtils::buildHeaderLines($this->headers);

        $curl = $this->initCurlRequest($this->url, $headerLines);

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
    public function post()
    {
        $headerLines = CurlUtils::buildHeaderLines($this->headers);

        $curl = $this->initCurlRequest($this->url, $headerLines);

        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $this->body);

        $response = curl_exec($curl);
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $headers = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);

        curl_close($curl);

        $headerLines = CurlUtils::parseResponseHeaders($headers);

        return [
            'body' => $responseBody,
            'httpCode' => $httpCode,
            'headers' => $headerLines
        ];
    }

    private function initCurlRequest(string $url, array $headerLines)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headerLines);
        return $curl;
    }
}
