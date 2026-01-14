<?php

namespace Tent;

/**
 * Utility class for handling HTTP headers in cURL requests and responses.
 *
 * Used by CurlHttpClient to build request headers and parse response headers.
 */
class CurlUtils
{
    /**
     * Builds an array of header lines for cURL from an associative array.
     *
     * Example: ['User-Agent' => 'Test'] becomes ['User-Agent: Test']
     *
     * @param array $headers Associative array of headers.
     * @return string[] Array of header lines in "Key: Value" format.
     */
    public static function buildHeaderLines(array $headers)
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = "$name: $value";
        }
        return $headerLines;
    }

    /**
     * Parses raw response headers from cURL into an array of header lines.
     *
     * Removes empty lines and HTTP status lines.
     *
     * @param string $headers Raw headers string from cURL response.
     * @return string[] Array of header lines in "Key: Value" format.
     */
    public static function parseResponseHeaders(string $headers)
    {
        $headerLines = explode("\n", $headers);
        $headerLines = array_map('trim', $headerLines);
        $headerLines = array_filter($headerLines, function ($header) {
            return !empty($header) && strpos($header, 'HTTP/') !== 0;
        });
        return $headerLines;
    }
}
