<?php

namespace Tent;

class ProxyRequestHandler
{
    private $targetHost;
    private $httpClient;

    public function __construct($targetHost, $httpClient = null)
    {
        $this->targetHost = $targetHost;
        $this->httpClient = $httpClient ?? new CurlHttpClient();
    }

    public function handleRequest($request)
    {
        // Build full URL from target host and request path
        $url = $this->targetHost . $request->requestUrl();
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
