<?php

namespace Tent\Utils;

use InvalidArgumentException;

class CacheFilePath
{
    /**
     * Constructs the cache file path based on the type, base path, and precomputed hash.
     *
     * @param string $type     The type of cache file ('body' or 'meta').
     * @param string $basePath The base path where the cache files are stored.
     * @param string $hash     The precomputed cache-key hash for the request (see {@see \Tent\Cache\RequestHasher}).
     *
     * @return string The constructed cache file path.
     *
     * @throws InvalidArgumentException If an invalid cache type is provided.
     */
    public static function path(string $type, string $basePath, string $hash): string
    {
        switch ($type) {
            case 'body':
                return $basePath . '/' . $hash . '.body.dat';
            case 'meta':
                return $basePath . '/' . $hash . '.meta.json';
            default:
                throw new InvalidArgumentException("Invalid cache type: $type");
        }
    }
}
