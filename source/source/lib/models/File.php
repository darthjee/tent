<?php

namespace Tent\Models;
use Tent\Models\FolderLocation;

class File
{
    private string $path;
    private FolderLocation $location;

    public function __construct(string $path, FolderLocation $location)
    {
        $this->path = $path;
        $this->location = $location;
    }

    public function fullPath(): string
    {
        return $this->location->basePath() . $this->path;
    }
}
