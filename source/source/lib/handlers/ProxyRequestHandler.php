<?php

namespace Tent\Handlers;

use Tent\Models\Server;
use Tent\Models\Request;
use Tent\Models\Response;
use Tent\Http\HttpClientInterface;
use Tent\Http\CurlHttpClient;
use Tent\Models\ForbiddenResponse;

/**
 * Handles HTTP requests by proxying them to a target server.
 *
 * This handler builds a new request based on the incoming request and forwards it
 * to the configured target server using an HTTP client. The response from the target
 * server is then returned as a Response object.
 */
class ProxyRequestHandler implements RequestHandler
{
    /**
     * @var Server The target server to which requests are proxied.
     */
    private $server;

    /**
     * @var HttpClientInterface The HTTP client used to make requests to the target server.
     */
    private $httpClient;

    /**
     * Constructs a ProxyRequestHandler.
     *
     * @param Server                   $server     The target server to which requests will be proxied.
     * @param HttpClientInterface|null $httpClient Optional HTTP client to use for requests. Defaults to CurlHttpClient.
     */
    public function __construct(Server $server, ?HttpClientInterface $httpClient = null)
    {
        $this->server = $server;
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
        $server = new Server($params['host'] ?? '');
        return new self($server);
    }

    /**
     * Proxies the incoming request to the target server and returns the response.
     *
     * @param Request $request The incoming HTTP request to be proxied.
     * @return Response The response from the target server.
     */
    public function handleRequest(Request $request)
    {
        // Build full URL from target host and request path
        $url = $this->server->targetHost() . $request->requestUrl();
        if ($request->query()) {
            $url .= '?' . $request->query();
        }

        $response = $this->httpClient->request($url, $request->headers());

        return new Response(
            $response['body'],
            $response['httpCode'],
            $response['headers']
        );
    }
}
