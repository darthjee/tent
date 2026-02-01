<?php

namespace Tent\Utils;

use InvalidArgumentException;

class CacheFilePath
{
    /**
     * Constructs the cache file path based on the type, base path, and query.
     *
     * @param string $type     The type of cache file ('body' or 'headers').
     * @param string $basePath The base path where the cache files are stored.
     * @param string $query    The query string associated with the request.
     *
     * @return string The constructed cache file path.
     *
     * @throws InvalidArgumentException If an invalid cache type is provided.
     */
    public static function path(string $type, string $basePath, string $query): string
    {
        $queryHash = hash('sha256', $query);
        switch ($type) {
            case 'body':
                return $basePath . '/' . $queryHash . '.body.txt';
            case 'headers':
                return $basePath . '/' . $queryHash . '.headers.json';
            case 'meta':
                return $basePath . '/' . $queryHash . '.meta.json';
            default:
                throw new InvalidArgumentException("Invalid cache type: $type");
        }
    }
}
