<?php

namespace Tent\Utils;

use Tent\Models\FolderLocation;

class FileUtils
{
    /**
     * Constructs the full file path by combining the base path from the FolderLocation
     * with the provided relative or absolute file path.
     *
     * @param string         $path     Relative or absolute file path.
     * @param string $location The base folder location.
     *
     * @return string The full file path.
     */
    public static function getFullPath(string $path, string $location): string
    {
        $base = rtrim($location, '/');
        $file = ltrim($path, '/');
        return $base . '/' . $file;
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
