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
}
