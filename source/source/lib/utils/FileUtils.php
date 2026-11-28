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
     * Checks, without accessing the filesystem, that a path cannot traverse out
     * of the directory it is relative to.
     *
     * Only whole segments are matched, so `...`, `..foo` or `%2e%2e` are valid
     * names. A NUL byte is also rejected, as filesystem functions throw on it.
     *
     * @param string $path Path made of `/`-separated segments.
     *
     * @return boolean True when there is no `.` or `..` segment, nor NUL byte.
     */
    public static function hasSafeSegments(string $path): bool
    {
        if (str_contains($path, "\0")) {
            return false;
        }

        return array_intersect(explode('/', $path), ['.', '..']) === [];
    }

    /**
     * Writes a file atomically, with mode `0644` whatever the umask.
     *
     * The content is written to a temporary file in the same directory, named
     * `<path>.tmp-<random hex>`, which is then renamed over `$path`. Readers see
     * either the previous file or the complete new one.
     *
     * A symbolic link standing at `$path` is replaced by the new file by the
     * `rename()`: it is never followed, so its target is left untouched. Links
     * among the parent directories of `$path` are followed as usual.
     *
     * The temporary file is removed when anything fails. It is only left behind
     * when the process dies before the rename. The parent directory is never
     * created.
     *
     * @param string $path    Path of the file to write.
     * @param string $content Content of the file.
     *
     * @return boolean True when the file was written, false otherwise. Nothing is thrown.
     */
    public static function writeAtomically(string $path, string $content): bool
    {
        $temporary = '';

        try {
            $temporary = $path . '.tmp-' . bin2hex(random_bytes(8));

            if (
                @file_put_contents($temporary, $content) === strlen($content)
                && @chmod($temporary, 0644)
                && @rename($temporary, $path)
            ) {
                return true;
            }
        } catch (\Throwable) {
            // Reported through the return value, like any other failure.
        }

        // False for a path with a NUL byte, on which unlink() would throw.
        if (file_exists($temporary)) {
            @unlink($temporary);
        }

        return false;
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
