<?php

namespace Tent;

class CurlHttpClient implements HttpClientInterface
{
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
