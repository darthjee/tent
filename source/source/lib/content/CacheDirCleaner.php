<?php

namespace Tent\Content;

use Tent\Models\FolderLocation;
use Tent\Log\Logger;
use Tent\Utils\FileUtils;
use Tent\Utils\RemovalResult;
use Tent\Utils\SafeRemover;

/**
 * Resolves and deletes file-cache directories for `collection`/`entity` targets.
 *
 * Used by `CacheCleanupMiddleware` to remove stale `GET/` cache directories
 * affected by a mutating request, without the middleware itself knowing how
 * cache directory paths are built or how directories are safely removed.
 *
 * ## Rename-then-delete
 *
 * A directory is first moved, with a single `rename()`, into the trash folder
 * `<location>/.tent-trash/<unix-ts>.<random>` (see {@see CacheTrash}) and only
 * then deleted. This makes the invalidation atomic for readers, lets only one
 * of several concurrent cleaners walk the tree, and keeps new cache writes out
 * of the tree being deleted. The trash folder itself is never removed.
 *
 * - a rename lost to another cleaner (the directory is already gone) is silent;
 * - when the directory cannot be moved (including when the trash folder is not
 *   a real directory), a `warn` is logged and it is deleted in place instead;
 * - targets resolving to the trash folder, or inside it, are ignored (unless
 *   their path is unsafe, see "Safety").
 *
 * ## Orphans
 *
 * A request interrupted while deleting (e.g. by `max_execution_time`) leaves an
 * entry behind in the trash. After its own deletion, every `clean()` and
 * `cleanPath()` call sweeps at most one of those orphans:
 *
 * - only entries moved into the trash at least `ORPHAN_GRACE_SECONDS` ago are
 *   orphans, as younger ones may still be under deletion;
 * - an orphan is claimed by renaming it to a new entry name, so that a single
 *   cleaner deletes it;
 * - files not named as trash entries are left alone;
 * - nothing is swept when the trash folder is not a real directory.
 *
 * ## Concurrency and failures
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
 * ## Safety
 *
 * A request path with a `.` or `..` segment, or a NUL byte (see
 * {@see FileUtils::hasSafeSegments()}), is refused up front, with a `warn`:
 * no directory is resolved nor deleted for it.
 *
 * Only paths genuinely under the configured cache location are touched. Before
 * anything is moved or deleted, a resolved directory is refused, with a `warn`,
 * when:
 *
 * - it is not under the location, or the location is empty or `/`;
 * - it has a `.` or `..` segment (or a NUL byte) after the location;
 * - its real parent directory is outside the real path of the location, e.g.
 *   through an intermediate symbolic link.
 *
 * The cache location itself may be a symbolic link, but a symbolic link to
 * somewhere outside of it cannot be used as a cache subdirectory. A link swapped
 * in between this check and the deletion is not detected.
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

    private const ORPHAN_GRACE_SECONDS = 600;

    private const MAX_ORPHANS_PER_CALL = 1;

    private FolderLocation $location;

    private CacheDirResolver $resolver;

    private SafeRemover $remover;

    private CacheTrash $trash;

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
        $this->trash = new CacheTrash($location);
    }

    /**
     * Resolves the cache directory for the given target and request path,
     * then deletes it if it exists.
     *
     * Nothing is deleted for the request path when it is unsafe (which is logged
     * at `warn` level), when the target cannot be meaningfully applied (e.g.
     * `entity` on a single-segment path) or when the resolved directory does not
     * exist. In every case, one orphan of the trash folder may then be swept.
     *
     * @param string $target Target type, either 'collection' or 'entity'.
     * @param string $path   Request path, e.g. '/users/1'.
     * @return void
     */
    public function clean(string $target, string $path): void
    {
        $dir = $this->acceptsPath($path) ? $this->resolver->resolve($target, $path) : null;

        if ($dir !== null) {
            $this->deleteDir($dir);
        }

        $this->sweepOrphan();
    }

    /**
     * Resolves the cache directory for an arbitrary, already-concrete path,
     * then deletes it if it exists.
     *
     * Unlike `clean()`, this has no `collection`/`entity` segment-count
     * restriction, so it can be used for explicit `custom` cleanup targets.
     *
     * Nothing is deleted for the request path when it is unsafe (which is logged
     * at `warn` level) or when the resolved directory does not exist. In every
     * case, one orphan of the trash folder may then be swept.
     *
     * @param string $path Concrete request path, e.g. '/games/space-invaders.json'.
     * @return void
     */
    public function cleanPath(string $path): void
    {
        $dir = $this->acceptsPath($path) ? $this->resolver->resolveExact($path) : null;

        if ($dir !== null) {
            $this->deleteDir($dir);
        }

        $this->sweepOrphan();
    }

    /**
     * Builds the iterator used to walk a directory, listing children before
     * their parent directory. Symbolic links inside the tree are not followed.
     *
     * Called once per walk attempt. This is a test seam, allowing specs to
     * simulate concurrent deletions, and is not part of the public API.
     *
     * @param string $dir Absolute path to the directory to walk: usually an entry
     *                    of the trash folder, or the cache directory itself when
     *                    it is deleted in place.
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
     * Moves a directory to another path, without emitting PHP warnings.
     *
     * This is a test seam, allowing specs to simulate lost races and rename
     * failures, and is not part of the public API.
     *
     * @param string $from Path of the directory to move.
     * @param string $to   New path of the directory.
     * @return boolean True when the directory was moved.
     */
    protected function renameDir(string $from, string $to): bool
    {
        return @rename($from, $to);
    }

    /**
     * Returns the current Unix timestamp, used to name and age trash entries.
     *
     * This is a test seam, allowing specs to age trash entries, and is not part
     * of the public API.
     *
     * @return integer The current Unix timestamp.
     */
    protected function now(): int
    {
        return time();
    }

    /**
     * Checks that a request path is safe to resolve into a cache directory,
     * logging a refusal otherwise.
     *
     * @param string $path Request path received to be cleaned.
     * @return boolean False when the path has a `.` or `..` segment, or a NUL byte.
     */
    private function acceptsPath(string $path): bool
    {
        if (FileUtils::hasSafeSegments($path)) {
            return true;
        }

        Logger::warn('cache cleanup refused — path: ' . $path . ', reason: unsafe path');

        return false;
    }

    /**
     * Invalidates a directory if it is safely scoped under the configured cache
     * location (see {@see self::isSafelyScoped()}) and does not resolve into the
     * trash folder.
     *
     * Nothing on the filesystem is changed unless both conditions hold.
     * Existence of the directory itself is not checked beforehand: it is decided
     * by the removal. A symbolic link standing in place of the directory is
     * removed without touching its target, while any other non-directory is left
     * alone.
     *
     * The debug line is only logged when this call removed the directory (or link).
     *
     * @param string $dir Absolute path to the directory to delete.
     * @return void
     */
    private function deleteDir(string $dir): void
    {
        if (!$this->isSafelyScoped($dir) || $this->trash->contains($dir)) {
            return;
        }

        if (is_link($dir)) {
            $this->logRemoval($dir, $this->remover->removeFile($dir));
        } elseif (!file_exists($dir) || is_dir($dir)) {
            $this->trashDir($dir);
        }
    }

    /**
     * Checks that a directory is genuinely under the configured cache location,
     * before anything is done to it.
     *
     * The checks run in this order:
     *
     * 1. the location must not be empty nor `/`;
     * 2. the path must start with `<location>/`;
     * 3. no segment after the location may be `.` or `..`, and no NUL byte may
     *    appear there (see {@see FileUtils::hasSafeSegments()});
     * 4. the real parent directory must not be outside the real location (see
     *    {@see self::resolvesInsideBase()}).
     *
     * Refusals are logged at `warn` level. Two cases are silent: when there is
     * nothing to delete (the location or the parent directory does not exist)
     * and when a path with a `.` or `..` segment is reserved for the trash.
     *
     * Request paths are already screened by {@see self::acceptsPath()}, so the
     * segment check here is only defence-in-depth.
     *
     * Known limitation (TOCTOU): a component swapped for a symbolic link between
     * this check and the `rename()` is not prevented. PHP offers no
     * `openat()`-style traversal to close that window, and exploiting it requires
     * write access inside the cache directory.
     *
     * @param string $dir Path to the directory to delete.
     * @return boolean True when the path stays under the cache location.
     */
    private function isSafelyScoped(string $dir): bool
    {
        $base = rtrim($this->location->basePath(), '/');
        if ($base === '') {
            return $this->refuse($dir, 'misconfigured cache location');
        }

        $prefix = $base . '/';
        if (!str_starts_with($dir, $prefix)) {
            return $this->refuse($dir, 'outside cache location');
        }

        if (!FileUtils::hasSafeSegments(substr($dir, strlen($prefix)))) {
            // Paths reserved for the trash are ignored silently, however spelled.
            return $this->trash->contains($dir) ? false : $this->refuse($dir, 'dot segment');
        }

        return $this->resolvesInsideBase($dir, $base);
    }

    /**
     * Checks that the real path of the parent directory is, or lies under, the
     * real path of the cache location, logging a refusal otherwise.
     *
     * Both sides are resolved, so the location may itself be a symbolic link.
     * The final component is never resolved: a link standing in place of the
     * directory is to be removed, not followed. The comparison is case-sensitive.
     *
     * The realpath cache is cleared first, as it may outlive the request and
     * hide a directory replaced by a link by another process.
     *
     * @param string $dir  Path to the directory to delete.
     * @param string $base Cache location, without a trailing slash.
     * @return boolean False when the parent is outside the location, or when
     *                 either of them does not exist (which is not logged).
     */
    private function resolvesInsideBase(string $dir, string $base): bool
    {
        clearstatcache(true);
        $realBase = realpath($base);
        $realParent = realpath(dirname($dir));

        if ($realBase === false || $realParent === false) {
            return false;
        }

        if ($realParent === $realBase || str_starts_with($realParent, $realBase . '/')) {
            return true;
        }

        return $this->refuse($dir, 'resolves outside cache location (' . $realParent . ')');
    }

    /**
     * Logs the refusal to delete a directory.
     *
     * @param string $dir    Path to the refused directory.
     * @param string $reason Why it was refused.
     * @return boolean Always false, so that checks can return it.
     */
    private function refuse(string $dir, string $reason): bool
    {
        Logger::warn('cache cleanup refused — dir: ' . $dir . ', reason: ' . $reason);

        return false;
    }

    /**
     * Moves a directory into the trash folder, then deletes it from there.
     *
     * Winning the rename is what invalidates the directory, so the debug line is
     * logged right then, whatever happens while deleting the trash entry. When
     * the directory cannot be moved, it is deleted in place.
     *
     * @param string $dir Absolute path to the directory to delete.
     * @return void
     */
    private function trashDir(string $dir): void
    {
        if (!$this->trash->ensure()) {
            $this->deleteInPlace($dir, 'cache trash is not a real directory — path: ' . $this->trash->path());
            return;
        }

        $entry = $this->trash->path() . '/' . $this->trash->newEntryName($this->now());
        $error = $this->attemptRename($dir, $entry);

        if ($error !== null) {
            $this->deleteInPlace($dir, 'cache trash rename failed — dir: ' . $dir . ', reason: ' . $error);
            return;
        }

        $this->logRemoval($dir, RemovalResult::Removed);
        $this->deleteEntry($entry);
    }

    /**
     * Renames a directory, reporting the reason when it fails.
     *
     * The error message is read right after the operation, so it always
     * describes the rename itself.
     *
     * @param string $from Path of the directory to move.
     * @param string $to   New path of the directory.
     * @return string|null Null when the directory was moved, otherwise the failure reason.
     */
    private function attemptRename(string $from, string $to): ?string
    {
        try {
            error_clear_last();
            if ($this->renameDir($from, $to)) {
                return null;
            }

            return error_get_last()['message'] ?? 'unknown error';
        } catch (\Throwable $exception) {
            return $exception->getMessage();
        }
    }

    /**
     * Fallback used when a directory could not be moved into the trash.
     *
     * A directory which no longer exists (e.g. another cleaner won the rename)
     * is silently ignored. Otherwise the failure is logged at `warn` level and
     * the directory is deleted where it is.
     *
     * @param string $dir     Absolute path to the directory to delete.
     * @param string $warning Warning describing why the directory was not moved.
     * @return void
     */
    private function deleteInPlace(string $dir, string $warning): void
    {
        if ($this->isGone($dir)) {
            return;
        }

        Logger::warn($warning);
        $this->logRemoval($dir, $this->removeDirRecursive($dir));
    }

    /**
     * Claims and deletes at most `MAX_ORPHANS_PER_CALL` orphans of the trash folder.
     *
     * An orphan claimed by another cleaner is silently skipped, in favour of the
     * next one. Any other claim failure is logged at `warn` level and ends the
     * sweep. The trash folder itself is never created nor removed.
     *
     * @return void
     */
    private function sweepOrphan(): void
    {
        if (!$this->trash->isUsable()) {
            return;
        }

        $now = $this->now();
        $claimed = 0;

        foreach ($this->orphanNames($now) as $name) {
            $orphan = $this->trash->path() . '/' . $name;
            $entry = $this->trash->path() . '/' . $this->trash->newEntryName($now);
            $error = $this->attemptRename($orphan, $entry);

            if ($error === null) {
                Logger::debug('cache trash orphan swept — entry: ' . $name);
                $this->deleteEntry($entry);

                if (++$claimed >= self::MAX_ORPHANS_PER_CALL) {
                    return;
                }
            } elseif (!$this->isGone($orphan)) {
                Logger::warn('cache trash orphan claim failed — entry: ' . $name . ', reason: ' . $error);
                return;
            }
        }
    }

    /**
     * Lists the entries of the trash folder which are past the grace period.
     *
     * Files not named as trash entries are never listed.
     *
     * @param integer $now Current Unix timestamp.
     * @return string[] Names of the orphan entries.
     */
    private function orphanNames(int $now): array
    {
        $names = @scandir($this->trash->path());

        return array_filter($names ?: [], function (string $name) use ($now): bool {
            $timestamp = $this->trash->entryTimestamp($name);

            return $timestamp !== null && $timestamp <= $now - self::ORPHAN_GRACE_SECONDS;
        });
    }

    /**
     * Deletes an entry of the trash folder.
     *
     * Anything other than a real directory (e.g. a symbolic link) is unlinked
     * without being followed.
     *
     * @param string $entry Path of the entry.
     * @return void
     */
    private function deleteEntry(string $entry): void
    {
        if (is_link($entry) || !is_dir($entry)) {
            $this->remover->removeFile($entry);
            return;
        }

        $this->removeDirRecursive($entry);
    }

    /**
     * Checks, after a failed operation, whether a path no longer exists.
     *
     * @param string $path Path to check.
     * @return boolean True when nothing, not even a symbolic link, stands at the path.
     */
    private function isGone(string $path): bool
    {
        // A failed rename() does not clear the stat cache.
        clearstatcache(true, $path);

        return !file_exists($path) && !is_link($path);
    }

    /**
     * Logs the debug line of a cleared directory, when this call removed it.
     *
     * @param string        $dir    Resolved path of the cache directory.
     * @param RemovalResult $result Outcome of its removal.
     * @return void
     */
    private function logRemoval(string $dir, RemovalResult $result): void
    {
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
