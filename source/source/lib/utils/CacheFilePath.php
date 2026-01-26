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
        switch ($type) {
            case 'body':
                return $basePath . '/cache.body.txt';
            case 'headers':
                return $basePath . '/cache.headers.json';
            default:
                throw new InvalidArgumentException("Invalid cache type: $type");
        }
    }
}
