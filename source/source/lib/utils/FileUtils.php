<?php

namespace Tent\Utils;

class FileUtils
{
    /**
     * Constructs the full file path by combining multiple path segments.
     *
     * @param string ...$paths Variable number of path segments to concatenate.
     * @return string The full file path.
     */
    public static function getFullPath(string ...$paths): string
    {
        $paths = array_map(function ($p, $i) use ($paths) {
            if ($i === 0) {
                return rtrim($p, '/');
            }
            return trim($p, '/');
        }, $paths, array_keys($paths));
        return implode('/', $paths);
    }

    /**
     * Lexically normalizes a path, without accessing the filesystem.
     *
     * Repeated slashes are collapsed, `.` segments are dropped and `..` segments
     * are resolved against the preceding segment. Absolute paths keep their
     * leading slash and relative paths stay relative (a leading `./` is dropped),
     * so two paths must both be normalized before being compared. A `..` climbing
     * above the first segment is kept as-is.
     *
     * Symbolic links are not resolved.
     *
     * @param string $path The path to normalize.
     *
     * @return string The normalized path, without a trailing slash (except for `/`).
     */
    public static function normalizePath(string $path): string
    {
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..' && $segments !== [] && end($segments) !== '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        $prefix = str_starts_with($path, '/') ? '/' : '';

        return $prefix . implode('/', $segments);
    }

    /**
     * Checks if a file exists at the given path and is a regular file.
     *
     * @param string $path The file path to check.
     *
     * @return boolean True if the file exists and is a regular file, false otherwise.
     */
    public static function exists(string $path): bool
    {
        return file_exists($path) && is_file($path);
    }
}
