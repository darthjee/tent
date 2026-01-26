<?php

namespace Tent\Utils;

use Tent\Models\RequestInterface;

class CacheFilePath
{
    public static function path(string $basePath, string $type, RequestInterface $request): string
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