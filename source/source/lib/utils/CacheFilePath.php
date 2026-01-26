<?php

namespace Tent\Utils;

class CacheFilePath
{
    public static function path(string $type, $basePath, ?string $query): string
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
