<?php

namespace Tent\Utils;

use Tent\Models\FolderLocation;

class FileUtils
{
    /**
     * Constructs the full file path by combining the base path from the FolderLocation
     * with the provided relative or absolute file path.
     *
     * @param string $location The base folder location.
     * @param string $path     Relative or absolute file path.
     *
     * @return string The full file path.
     */
    public static function getFullPath($paths): string
    {
        $count = count($paths);
        $paths = array_map(function ($p, $i) use ($count, $paths) {
            if ($i === 0) {
                return rtrim($p, '/');
            } elseif ($i === $count - 1) {
                return ltrim($p, '/');
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
