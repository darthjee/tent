<?php

namespace Tent\Content;

use Tent\Models\FolderLocation;
use Tent\Utils\FileUtils;

/**
 * Describes the trash folder of a cache location, `<base>/.tent-trash`.
 *
 * Cache directories being invalidated are moved into this folder before being
 * deleted. It lives inside the cache root so that the move never crosses
 * filesystems, which makes its path a reserved one: it must never be read or
 * written as a cache directory.
 *
 * This class is the single source of truth for the folder name, the layout of
 * its entry names (`<unix-ts>.<16 hex chars>`) and the reserved path rule.
 *
 * ## Example
 *
 * ```php
 * $trash = new CacheTrash(new FolderLocation('./cache'));
 *
 * $trash->contains('./cache/.tent-trash/entry/GET'); // true
 * $trash->contains('./cache/users/GET');             // false
 *
 * if ($trash->ensure()) {
 *     $target = $trash->path() . '/' . $trash->newEntryName(time());
 * }
 * ```
 */
class CacheTrash
{
    public const NAME = '.tent-trash';

    private const ENTRY_PATTERN = '/^(\d+)\.[0-9a-f]{16}$/D';

    private FolderLocation $location;

    /**
     * @param FolderLocation $location Base cache directory.
     */
    public function __construct(FolderLocation $location)
    {
        $this->location = $location;
    }

    /**
     * Returns the path of the trash folder.
     *
     * @return string The trash folder path, under the cache location.
     */
    public function path(): string
    {
        return FileUtils::getFullPath($this->location->basePath(), self::NAME);
    }

    /**
     * Checks whether a resolved cache path is the trash folder or lies under it.
     *
     * Both paths are lexically normalized, and compared case-insensitively so
     * that case-insensitive filesystems cannot be used to reach the folder.
     *
     * @param string $path Resolved path, including the cache location.
     * @return boolean True when the path is reserved for the trash.
     */
    public function contains(string $path): bool
    {
        $trash = strtolower(FileUtils::normalizePath($this->path()));
        $target = strtolower(FileUtils::normalizePath($path));

        return $target === $trash || str_starts_with($target, $trash . '/');
    }

    /**
     * Creates the trash folder when it is missing and checks that it can be used.
     *
     * The folder is created with mode `0755`, which the umask may only reduce.
     * The cache location itself is not created. Anything already standing in
     * place of the folder is never deleted nor repaired.
     *
     * @return boolean True when the trash folder is usable.
     */
    public function ensure(): bool
    {
        $path = $this->path();

        // May fail because the folder already exists, so the result is checked below.
        @mkdir($path, 0755);
        clearstatcache(true, $path);

        return $this->isUsable();
    }

    /**
     * Checks that the trash folder is a real directory, not a symbolic link.
     *
     * @return boolean True when entries can safely be moved into and deleted from it.
     */
    public function isUsable(): bool
    {
        $path = $this->path();

        return is_dir($path) && !is_link($path);
    }

    /**
     * Builds a new, unique entry name.
     *
     * @param integer $now Unix timestamp of the move into the trash.
     * @return string The entry name, as `<unix-ts>.<16 hex chars>`.
     */
    public function newEntryName(int $now): string
    {
        return $now . '.' . bin2hex(random_bytes(8));
    }

    /**
     * Reads the timestamp of an entry name.
     *
     * @param string $name Entry name, without any directory.
     * @return integer|null The Unix timestamp, or null when the name is not a trash entry name.
     */
    public function entryTimestamp(string $name): ?int
    {
        if (preg_match(self::ENTRY_PATTERN, $name, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
