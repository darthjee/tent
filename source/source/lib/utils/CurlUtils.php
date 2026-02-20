<?php

namespace Tent\Utils;

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

    /**
     * Converts header lines in "Name: Value" format to an associative array.
     *
     * Header names are normalized to lowercase and values are trimmed.
     * Invalid header lines (without a colon separator) are ignored.
     *
     * @param string[] $headerLines Array of header lines.
     * @return array Associative array where key is lowercase header name and value is header value.
     */
    public static function mapHeaderLines(array $headerLines): array
    {
        $headers = [];
        foreach ($headerLines as $headerLine) {
            $parts = explode(':', $headerLine, 2);
            if (count($parts) === 2) {
                $name = strtolower(trim($parts[0]));
                $value = trim($parts[1]);
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
