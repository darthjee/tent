<?php

namespace Tent\RequestHandlers;

use Tent\Models\Server;
use Tent\Models\RequestInterface;
use Tent\Models\Response;
use Tent\Http\HttpClientInterface;
use Tent\Http\CurlHttpClient;
use Tent\Http\UpstreamFailureNormalizer;
use InvalidArgumentException;
use Tent\Log\Logger;

/**
 * Handles HTTP requests by proxying them to a target server.
 *
 * This handler builds a new request based on the incoming request and forwards it
 * to the configured target server using an HTTP client. The response from the target
 * server is then returned as a Response object.
 *
 * @example Basic proxy configuration:
 * ```php
 * Configuration::buildRule([
 *     'handler' => [
 *         'type' => 'proxy',
 *         'host' => 'http://api:80'
 *     ],
 *     'matchers' => [
 *         ['method' => 'GET', 'uri' => '/persons', 'type' => 'exact']
 *     ]
 * ]);
 * ```
 *
 * @example Proxy configuration with Custom headers and cache
 * ```php
 * Configuration::buildRule([
 *     'handler' => [
 *         'type' => 'proxy',
 *         'host' => 'http://api:80'
 *     ],
 *     'matchers' => [
 *          ['method' => 'GET', 'uri' => '.json', 'type' => 'ends_with']
 *     ],
 *     "middlewares" => [
 *         [
 *              'class' => 'Tent\Middlewares\FileCacheMiddleware',
 *              'location' => "./cache",
 *              'matchers' => [
 *                  [
 *                      'class'     => 'Tent\Matchers\StatusCodeMatcher',
 *                      'httpCodes' => ["2xx", "3xx"]
 *                  ]
 *              ]
 *          ],
 *          [
 *              'class' => 'Tent\Middlewares\RenameHeaderMiddleware',
 *              'from'  => 'Host',
 *              'to'    => 'X-Forwarded-Host'
 *          ],
 *          [
 *              'class' => 'Tent\Middlewares\SetHeadersMiddleware',
 *              'headers' => [
 *                  'Host' => 'api:80'
 *              ]
 *          ]
 *      ]
 * ]);
 * ```
 */
class ProxyRequestHandler extends RequestHandler
{
    /**
     * @var Server The target server to which requests are proxied.
     */
    private Server $server;

    /**
     * @var HttpClientInterface The HTTP client used to make requests to the target server.
     */
    private HttpClientInterface $httpClient;

    /**
     * Constructs a ProxyRequestHandler.
     *
     * @param string                   $host       The target host to which requests will be proxied.
     * @param HttpClientInterface|null $httpClient Optional HTTP client to use for requests. Defaults to CurlHttpClient.
     *          It is always wrapped in an UpstreamFailureNormalizer (502/504 on transport failures).
     * @param int                      $connectTimeout Connection timeout in seconds (default 5).
     * @param int                      $timeout        Total request timeout in seconds (default 30).
     */
    public function __construct(
        string $host,
        ?HttpClientInterface $httpClient = null,
        int $connectTimeout = 5,
        int $timeout = 30
    ) {
        $this->server = new Server($host);
        $this->httpClient = new UpstreamFailureNormalizer(
            $httpClient ?? new CurlHttpClient(['connect_timeout' => $connectTimeout, 'timeout' => $timeout])
        );
    }

    /**
     * Builds a ProxyRequestHandler using named parameters.
     *
     * Example:
     *   ProxyRequestHandler::build(['host' => 'http://api.com'])
     *
     * @param array $params Associative array with keys:
     *   - 'host' (string)
     *   - 'connect_timeout' (int, optional): connection timeout in seconds. Defaults to 5.
     *   - 'timeout' (int, optional): total request timeout in seconds. Defaults to 30.
     * @return ProxyRequestHandler
     * @throws InvalidArgumentException If a timeout is not a non-negative integer.
     */
    public static function build(array $params): self
    {
        $host = $params['host'] ?? '';
        $connectTimeout = self::timeoutParam($params, 'connect_timeout', 5);
        $timeout = self::timeoutParam($params, 'timeout', 30);
        return new self($host, null, $connectTimeout, $timeout);
    }

    /**
     * Proxies the incoming request to the target server and returns the response.
     *
     * @param RequestInterface $request The incoming HTTP request to be proxied.
     * @return Response The response from the target server.
     */
    protected function processsRequest(RequestInterface $request): Response
    {
        $url     = $this->server()->fullUrl($request->requestPath(), $request->query());
        $headers = $request->headers();
        $files   = $request->uploadedFiles();

        if (!empty($files)) {
            // Strip Content-Type so curl generates a new multipart boundary that
            // matches the forwarded body (the original boundary is no longer valid).
            unset($headers['Content-Type'], $headers['content-type']);
        }

        $response = $this->httpClient->request(
            $request->requestMethod(),
            $url,
            $headers,
            $request->body(),
            $files,
            $request->postFields()
        );
        $response['request'] = $request;

        $result = new Response($response);

        // The query string may carry credentials, so it is never logged.
        $loggedUrl = $this->server()->fullUrl($request->requestPath());

        Logger::debug(
            '[' . $result->httpCode() . '] - upstream response — method: ' . $request->requestMethod() .
            ', uri: ' . $request->requestPath() .
            ', upstream: ' . $loggedUrl
        );

        return $result;
    }

    /**
     * Reads a timeout parameter, validating it is a non-negative integer.
     *
     * @param array  $params  The build parameters.
     * @param string $key     The parameter key.
     * @param int    $default The value used when the key is absent.
     * @return int
     * @throws InvalidArgumentException If the value is not a non-negative integer.
     */
    protected static function timeoutParam(array $params, string $key, int $default): int
    {
        if (!array_key_exists($key, $params)) {
            return $default;
        }

        $value = $params[$key];

        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException("Parameter '$key' must be a non-negative integer");
        }

        return $value;
    }

    /**
     * Lazily initializes and returns the Server instance for the target host.
     *
     * @return Server The Server instance representing the target server.
     */
    private function server(): Server
    {
        return $this->server;
    }

    /**
     * Returns the host component of the target server, including port if specified.
     *
     * @return string
     */
    protected function host(): string
    {
        return $this->server()->host();
    }
}
