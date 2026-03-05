<?php

namespace Tent\RequestHandlers;

use Tent\Models\Server;
use Tent\Models\RequestInterface;
use Tent\Models\Response;
use Tent\Http\HttpClientInterface;
use Tent\Http\CurlHttpClient;

/**
 * Handles HTTP requests by proxying them to a target server.
 *
 * This handler builds a new request based on the incoming request and forwards it
 * to the configured target server using an HTTP client. The response from the target
 * server is then returned as a Response object.
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
     */
    public function __construct(string $host, ?HttpClientInterface $httpClient = null)
    {
        $this->server = new Server($host);
        $this->httpClient = $httpClient ?? new CurlHttpClient();
    }

    /**
     * Builds a ProxyRequestHandler using named parameters.
     *
     * Example:
     *   ProxyRequestHandler::build(['host' => 'http://api.com'])
     *
     * @param array $params Associative array with key 'host' (string).
     * @return ProxyRequestHandler
     */
    public static function build(array $params): self
    {
        $host = $params['host'] ?? '';
        return new self($host);
    }

    /**
     * Proxies the incoming request to the target server and returns the response.
     *
     * @param RequestInterface $request The incoming HTTP request to be proxied.
     * @return Response The response from the target server.
     */
    protected function processsRequest(RequestInterface $request): Response
    {
        // Build full URL from target host and request path
        $url = $this->server()->fullUrl($request->requestPath(), $request->query());

        $response = $this->httpClient->request($request->requestMethod(), $url, $request->headers(), $request->body());
        $response['request'] = $request;

        return new Response($response);
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
