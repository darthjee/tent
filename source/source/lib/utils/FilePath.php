<?php

namespace Tent\Utils;

use Tent\Models\FolderLocation;

class FilePath
{
    public static function getFullPath(string $path, FolderLocation $location): string
    {
        return $location->basePath() . $path;
    }
}
