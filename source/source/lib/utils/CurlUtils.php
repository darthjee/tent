<?php

namespace Tent;

class CurlUtils
{
    public static function buildHeaderLines($headers)
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = "$name: $value";
        }
        return $headerLines;
    }

    public static function parseResponseHeaders($headers)
    {
        $headerLines = explode("\n", $headers);
        $headerLines = array_map('trim', $headerLines);
        $headerLines = array_filter($headerLines, function ($header) {
            return !empty($header) && strpos($header, 'HTTP/') !== 0;
        });
        return $headerLines;
    }
}
