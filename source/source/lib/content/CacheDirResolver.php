<?php

namespace Tent\Content;

use Tent\Models\FolderLocation;
use Tent\Utils\FileUtils;

/**
 * Resolves the cache directory path for a given target and request path.
 *
 * Used by `CacheDirCleaner` to determine which `GET/` cache directory
 * corresponds to a `collection` or `entity` target, without it knowing how
 * cache directory paths are built from a request path.
 *
 * ## Example
 *
 * ```php
 * $location = new FolderLocation('./cache');
 * $resolver = new CacheDirResolver($location);
 *
 * $resolver->resolve('collection', '/users/1');
 * $resolver->resolve('entity', '/users/1');
 * ```
 */
class CacheDirResolver
{
    private FolderLocation $location;

    /**
     * @param FolderLocation $location Base cache directory (must match FileCacheMiddleware).
     */
    public function __construct(FolderLocation $location)
    {
        $this->location = $location;
    }

    /**
     * Resolves the cache directory for a given target and request path.
     *
     * Returns null when the target cannot be meaningfully applied
     * (e.g. `entity` on a single-segment path) or is unknown.
     *
     * @param string $target Target type, either 'collection' or 'entity'.
     * @param string $path   Request path, e.g. '/users/1'.
     * @return string|null
     */
    public function resolve(string $target, string $path): ?string
    {
        $base = $this->location->basePath();
        $segments = array_values(array_filter(explode('/', $path)));

        if ($target === 'collection') {
            $collectionSegments = count($segments) > 1 ? array_slice($segments, 0, -1) : $segments;
            $collectionPath = implode('/', $collectionSegments);
            return FileUtils::getFullPath($base, $collectionPath, 'GET');
        }

        if ($target === 'entity') {
            if (count($segments) < 2) {
                return null;
            }
            return FileUtils::getFullPath($base, implode('/', $segments), 'GET');
        }

        return null;
    }
}
