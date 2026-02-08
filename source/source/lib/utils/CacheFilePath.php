<?php

namespace Tent\Utils;

use InvalidArgumentException;

class CacheFilePath
{
    /**
     * Constructs the cache file path based on the type, base path, HTTP method, and query.
     *
     * @param string $type     The type of cache file ('body' or 'meta').
     * @param string $basePath The base path where the cache files are stored.
     * @param string $method   The HTTP request method (e.g., GET, POST, PUT, DELETE).
     * @param string $query    The query string associated with the request.
     *
     * @return string The constructed cache file path.
     *
     * @throws InvalidArgumentException If an invalid cache type is provided.
     */
    public static function path(string $type, string $basePath, string $method, string $query): string
    {
        $queryHash = hash('sha256', $query);
        $methodPath = $basePath . '/' . $method;
        switch ($type) {
            case 'body':
                return $methodPath . '/' . $queryHash . '.body.dat';
            case 'meta':
                return $methodPath . '/' . $queryHash . '.meta.json';
            default:
                throw new InvalidArgumentException("Invalid cache type: $type");
        }
    }
}
