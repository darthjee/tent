<?php

namespace Tent\Models;

/**
 * Represents a base directory location.
 *
 * Used by StaticFileHandler to determine where to look for files to serve, and
 * as the cache location of the file cache (e.g. FileCache and CacheDirCleaner),
 * which relies on {@see contains()} to never write nor delete outside of it.
 */
class FolderLocation
{
    private $basePath;

    /**
     * @param string $basePath The base directory path.
     */
    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * Returns the base directory path.
     *
     * @return string The base path.
     */
    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * Checks that the real path of a file or directory is, or lies under, the
     * real path of the base directory.
     *
     * Both sides are resolved with `realpath()`, so symbolic links are followed:
     * the base directory may itself be a link, while a link below it only passes
     * when it points back inside. The comparison is case-sensitive.
     *
     * The realpath cache is cleared first, as it may outlive the request and
     * hide a directory replaced by a link by another process.
     *
     * @param string $path Path to check, which must exist.
     * @return boolean False when the path is outside the base directory, or when
     *                 either of them cannot be resolved.
     */
    public function contains(string $path): bool
    {
        clearstatcache(true);
        $realBase = realpath($this->basePath);
        $realPath = realpath($path);

        if ($realBase === false || $realPath === false) {
            return false;
        }

        return $realPath === $realBase || str_starts_with($realPath, $realBase . '/');
    }
}
