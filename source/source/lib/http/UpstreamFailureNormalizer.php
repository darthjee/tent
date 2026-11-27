<?php

namespace Tent\Http;

use Tent\Log\Logger;

/**
 * Decorator that turns transport-level upstream failures into 502/504 results.
 *
 * Wraps another HttpClientInterface. When the decorated client reports a cURL
 * error (or a zero HTTP code), the result is replaced by an empty 502 (or 504
 * on timeout). Upstream error text and host details never reach the result;
 * they are only written to the log, without the query string.
 */
class UpstreamFailureNormalizer implements HttpClientInterface
{
    /**
     * @var HttpClientInterface The decorated client
     */
    private HttpClientInterface $client;

    /**
     * @param HttpClientInterface $client The client to decorate.
     */
    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * {@inheritdoc}
     */
    public function request(
        string $method,
        string $url,
        array $headers,
        ?string $body = null,
        array $uploadedFiles = [],
        array $postFields = []
    ): array {
        $result = $this->client->request($method, $url, $headers, $body, $uploadedFiles, $postFields);

        $errno = $result['errno'] ?? 0;
        $error = $result['error'] ?? '';
        $httpCode = $result['httpCode'] ?? 0;

        if ($errno === CURLE_OPERATION_TIMEDOUT) {
            return $this->failure(504, 'upstream timeout', $method, $url, $errno, $error);
        }

        if ($errno !== 0 || $httpCode === 0) {
            return $this->failure(502, 'upstream unreachable', $method, $url, $errno, $error);
        }

        unset($result['errno'], $result['error']);

        return $result;
    }

    /**
     * Logs the failure and builds the synthesized result.
     *
     * @param integer $code   The HTTP code to return.
     * @param string  $reason The log reason.
     * @param string  $method The request method.
     * @param string  $url    The request URL.
     * @param integer $errno  The cURL errno.
     * @param string  $error  The cURL error message.
     *
     * @return array
     */
    private function failure(int $code, string $reason, string $method, string $url, int $errno, string $error): array
    {
        $cleanUrl = strtok($url, '?');

        Logger::warn(
            "[$code] - $reason — method: " . strtoupper($method)
            . ", url: $cleanUrl, errno: $errno, error: $error"
        );

        return ['body' => '', 'httpCode' => $code, 'headers' => []];
    }
}
