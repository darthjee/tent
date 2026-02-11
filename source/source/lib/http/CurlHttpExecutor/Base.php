<?php

namespace Tent\Http\CurlHttpExecutor;

use Tent\Utils\CurlUtils;
use CurlHandle;

/**
 * Base class for cURL HTTP executors.
 * Handles common setup and response parsing logic.
 */
abstract class Base
{
    /**
     * @var string The target URL for the request
     */
    protected string $url;

    /**
     * @var array Associative array of headers to send (e.g., ['User-Agent' => 'Test'])
     */
    protected array $headers;

    /**
     * @var string|null The request body/payload to send (for POST requests)
     */
    protected ?string $body;

    /**
     * @var CurlHandle|null The cURL handle for the request
     */
    protected ?CurlHandle $curlHandle;

    /**
     * Initializes the executor with the given options.
     *
     * @param array $options Associative array with keys:
     *  - 'url': The target URL for the request
     * - 'headers': Associative array of headers to send
     * - 'body': (optional) The request body/payload to send (for POST requests)
     */
    public function __construct(array $options)
    {
        $this->url = $options['url'] ?? '';
        $this->headers = $options['headers'] ?? [];
        $this->body = $options['body'] ?? null;
    }

    /**
     * Executes the HTTP request and returns the response.
     *
     * This method must be implemented by subclasses to perform the specific HTTP method (GET, POST, etc.).
     *
     * @return array{
     *   body: string,
     *   httpCode: int,
     *   headers: string[]
     * } Array with response body, status code, and headers.
     */
    public function request(): array
    {
        $this->initCurlRequest();
        $this->addExtraCurlOptions();

        return $this->executeCurlRequest();
    }

    /**
     * Adds any extra cURL options specific to the HTTP method.
     *
     * Subclasses can override this method to set additional cURL options (e.g., for POST requests).
     */
    protected function addExtraCurlOptions(): void
    {
    }

    /**
     * Initializes the cURL request with common options.
     * This method sets up the cURL handle with the target URL, headers, and common options for all requests.
     */
    protected function initCurlRequest(): void
    {
        $headerLines = CurlUtils::buildHeaderLines($this->headers);

        $this->curlHandle = curl_init($this->url);

        curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curlHandle, CURLOPT_HEADER, true);
        curl_setopt($this->curlHandle, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->curlHandle, CURLOPT_HTTPHEADER, $headerLines);
    }

    /**
     * Executes the cURL request and processes the response.
     *
     * This method performs the cURL execution, parses the response headers and body, and returns them in a structured format.
     *
     * @return array{
     *   body: string,
     *   httpCode: int,
     *   headers: string[]
     * } Array with response body, status code, and headers.
     */
    protected function executeCurlRequest(): array
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
