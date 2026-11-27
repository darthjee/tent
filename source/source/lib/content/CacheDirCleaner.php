<?php

namespace Tent\Content;

use Tent\Models\FolderLocation;
use Tent\Log\Logger;
use Tent\Utils\RemovalResult;
use Tent\Utils\SafeRemover;

/**
 * Resolves and deletes file-cache directories for `collection`/`entity` targets.
 *
 * Used by `CacheCleanupMiddleware` to remove stale `GET/` cache directories
 * affected by a mutating request, without the middleware itself knowing how
 * cache directory paths are built or how directories are safely removed.
 *
 * Deletion tolerates other writers removing the same entries concurrently:
 *
 * - anything already gone counts as success and is not logged;
 * - a walk interrupted by a vanished (or unreadable) directory is restarted
 *   from the root, with a bounded number of attempts;
 * - real removal failures are logged at `warn` level by {@see SafeRemover}
 *   and are never thrown;
 * - symbolic links are removed without being followed, including when the
 *   resolved directory is itself a link.
 *
 * ## Example
 *
 * ```php
 * $location = new FolderLocation('./cache');
 * $cleaner = new CacheDirCleaner($location);
 *
 * $cleaner->clean('collection', '/users/1');
 * $cleaner->clean('entity', '/users/1');
 * $cleaner->cleanPath('/users.json');
 * ```
 */
class CacheDirCleaner
{
    private const MAX_WALK_ATTEMPTS = 3;

    private FolderLocation $location;

    private CacheDirResolver $resolver;

    private SafeRemover $remover;

    /**
     * @param FolderLocation   $location Base cache directory (must match FileCacheMiddleware).
     * @param SafeRemover|null $remover  Optional helper used to remove files and directories.
     *                                   Defaults to {@see SafeRemover}.
     */
    public function __construct(FolderLocation $location, ?SafeRemover $remover = null)
    {
        $this->location = $location;
        $this->resolver = new CacheDirResolver($location);
        $this->remover = $remover ?? new SafeRemover();
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
     * Resolves the cache directory for an arbitrary, already-concrete path,
     * then deletes it if it exists.
     *
     * Unlike `clean()`, this has no `collection`/`entity` segment-count
     * restriction, so it can be used for explicit `custom` cleanup targets.
     *
     * @param string $path Concrete request path, e.g. '/games/space-invaders.json'.
     * @return void
     */
    public function cleanPath(string $path): void
    {
        $this->deleteDir($this->resolver->resolveExact($path));
    }

    /**
     * Builds the iterator used to walk a directory, listing children before
     * their parent directory. Symbolic links inside the tree are not followed.
     *
     * Called once per walk attempt. This is a test seam, allowing specs to
     * simulate concurrent deletions, and is not part of the public API.
     *
     * @param string $dir Absolute path to the directory to walk.
     * @return \Iterator
     * @throws \UnexpectedValueException When the directory cannot be opened.
     */
    protected function createIterator(string $dir): \Iterator
    {
        return new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
    }

    /**
     * Deletes a directory and all its contents if it is safely scoped under
     * the configured cache location.
     *
     * Existence is not checked beforehand: it is decided by the removal itself.
     * A symbolic link standing in place of the directory is removed without
     * touching its target, while any other non-directory is left alone.
     *
     * The debug line is only logged when this call removed the directory (or link).
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

        if (is_link($dir)) {
            $result = $this->remover->removeFile($dir);
        } elseif (file_exists($dir) && !is_dir($dir)) {
            return;
        } else {
            $result = $this->removeDirRecursive($dir);
        }

        if ($result === RemovalResult::Removed) {
            Logger::debug('cache cleared — dir: ' . $dir);
        }
    }

    /**
     * Recursively removes a directory and all its contents.
     *
     * A walk interrupted because a directory could not be opened is restarted
     * from the root, up to `MAX_WALK_ATTEMPTS` attempts in total, so that
     * entries not yet visited are still removed. Failures to remove individual
     * entries do not restart the walk.
     *
     * @param string $dir Absolute path to the directory to remove.
     * @return RemovalResult The outcome of the removal of the directory itself.
     */
    private function removeDirRecursive(string $dir): RemovalResult
    {
        for ($attempt = 1; $attempt <= self::MAX_WALK_ATTEMPTS; $attempt++) {
            try {
                foreach ($this->createIterator($dir) as $item) {
                    if ($item->isLink() || $item->isFile()) {
                        $this->remover->removeFile($item->getPathname());
                    } elseif ($item->isDir()) {
                        $this->remover->removeDir($item->getPathname());
                    }
                }
                break;
            } catch (\UnexpectedValueException) {
                // A failed directory read does not clear the stat cache.
                clearstatcache(true, $dir);
                if (!file_exists($dir) && !is_link($dir)) {
                    return RemovalResult::AlreadyGone;
                }
            }
        }

        return $this->remover->removeDir($dir);
    }
}
