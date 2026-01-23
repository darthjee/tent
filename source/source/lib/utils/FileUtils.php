<?php

namespace Tent\Utils;

use Tent\Models\FolderLocation;

class FileUtils
{
    public static function getFullPath(string $path, FolderLocation $location): string
    {
        $base = rtrim($location->basePath(), '/');
        $file = ltrim($path, '/');
        return $base . '/' . $file;
    }

    public static function exists(string $path): bool
    {
        return file_exists($path) && is_file($path);
    }
}
