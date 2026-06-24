<?php

namespace Tent\Content;

use Tent\Models\FolderLocation;
use Tent\Log\Logger;

/**
 * Resolves and deletes file-cache directories for `collection`/`entity` targets.
 *
 * Used by `CacheCleanupMiddleware` to remove stale `GET/` cache directories
 * affected by a mutating request, without the middleware itself knowing how
 * cache directory paths are built or how directories are safely removed.
 *
 * ## Example
 *
 * ```php
 * $location = new FolderLocation('./cache');
 * $cleaner = new CacheDirCleaner($location);
 *
 * $cleaner->clean('collection', '/users/1');
 * $cleaner->clean('entity', '/users/1');
 * ```
 */
class CacheDirCleaner
{
    private FolderLocation $location;

    private CacheDirResolver $resolver;

    /**
     * @param FolderLocation $location Base cache directory (must match FileCacheMiddleware).
     */
    public function __construct(FolderLocation $location)
    {
        $this->location = $location;
        $this->resolver = new CacheDirResolver($location);
    }

    /**
     * Resolves the cache directory for the given target and request path,
     * then deletes it if it exists.
     *
     * Does nothing when the target cannot be meaningfully applied
     * (e.g. `entity` on a single-segment path) or when the resolved
     * directory does not exist.
     *
     * @param string $target Target type, either 'collection' or 'entity'.
     * @param string $path   Request path, e.g. '/users/1'.
     * @return void
     */
    public function clean(string $target, string $path): void
    {
        $dir = $this->resolver->resolve($target, $path);

        if ($dir !== null) {
            $this->deleteDir($dir);
        }
    }

    /**
     * Recursively deletes a directory and all its contents if it exists and is
     * safely scoped under the configured cache location.
     *
     * @param string $dir Absolute path to the directory to delete.
     * @return void
     */
    private function deleteDir(string $dir): void
    {
        $base = rtrim($this->location->basePath(), '/');
        if (!str_starts_with($dir, $base . '/')) {
            return;
        }

        if (!is_dir($dir)) {
            return;
        }

        $this->removeDirRecursive($dir);
        Logger::debug('cache cleared — dir: ' . $dir);
    }

    /**
     * Recursively removes a directory and all its contents.
     *
     * @param string $dir Absolute path to the directory to remove.
     * @return void
     */
    private function removeDirRecursive(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isFile() || $item->isLink()) {
                unlink($item->getPathname());
            } elseif ($item->isDir()) {
                rmdir($item->getPathname());
            }
        }
        rmdir($dir);
    }
}
