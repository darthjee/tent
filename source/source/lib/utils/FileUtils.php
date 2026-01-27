<?php

namespace Tent\Utils;

use Tent\Models\FolderLocation;

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
            } else {
                return trim($p, '/');
            }
        }, $paths, array_keys($paths));
        return implode('/', $paths);
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
