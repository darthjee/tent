<?php

namespace Tent\Http\CurlHttpExecutor;

use Tent\Utils\CurlUtils;
use CurlHandle;

abstract class Base
{
    private string $url;
    private array $headers;
    private ?string $body;
    private ?CurlHandle $curlHandle;

    public function __construct(array $options)
    {
        $this->url = $options['url'] ?? '';
        $this->headers = $options['headers'] ?? [];
        $this->body = $options['body'] ?? null;
    }

    abstract public function request();

    protected function initCurlRequest()
    {
        $headerLines = CurlUtils::buildHeaderLines($this->headers);

        $this->curlHandle = curl_init($this->url);

        curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curlHandle, CURLOPT_HEADER, true);
        curl_setopt($this->curlHandle, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->curlHandle, CURLOPT_HTTPHEADER, $headerLines);
    }

    protected function executeCurlRequest()
    {
        $response = curl_exec($this->curlHandle);
        $headerSize = curl_getinfo($this->curlHandle, CURLINFO_HEADER_SIZE);
        $httpCode = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);

        $headers = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);

        curl_close($this->curlHandle);

        $headerLines = CurlUtils::parseResponseHeaders($headers);

        return [
            'body' => $responseBody,
            'httpCode' => $httpCode,
            'headers' => $headerLines
        ];
    }
}
