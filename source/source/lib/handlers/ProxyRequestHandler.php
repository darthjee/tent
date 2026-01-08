<?php

namespace Tent;

class ProxyRequestHandler implements RequestHandler
{
    private $server;
    private $httpClient;

    public function __construct($server, $httpClient = null)
    {
        $this->server = $server;
        $this->httpClient = $httpClient ?? new CurlHttpClient();
    }

    public function handleRequest($request)
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
