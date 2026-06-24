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
        $segments = array_values(array_filter(explode('/', $path)));

        if ($target === 'collection') {
            return $this->resolveCollection($segments);
        }

        if ($target === 'entity') {
            return $this->resolveEntity($segments);
        }

        return null;
    }

    /**
     * Resolves the cache directory for a `collection` target.
     *
     * @param array $segments Request path segments.
     * @return string
     */
    private function resolveCollection(array $segments): string
    {
        $base = $this->location->basePath();
        $collectionSegments = count($segments) > 1 ? array_slice($segments, 0, -1) : $segments;
        $collectionPath = implode('/', $collectionSegments);

        return FileUtils::getFullPath($base, $collectionPath, 'GET');
    }

    /**
     * Resolves the cache directory for an `entity` target.
     *
     * Returns null when the path does not have enough segments to
     * represent an entity (e.g. a single-segment path).
     *
     * @param array $segments Request path segments.
     * @return string|null
     */
    private function resolveEntity(array $segments): ?string
    {
        if (count($segments) < 2) {
            return null;
        }

        $base = $this->location->basePath();

        return FileUtils::getFullPath($base, implode('/', $segments), 'GET');
    }
}
