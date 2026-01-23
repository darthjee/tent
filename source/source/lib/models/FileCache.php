<?php

namespace Tent\Models;

use Tent\Models\FolderLocation;

class FileCache
{
    /**
     * @var string Relative or absolute file path.
     */
    private string $path;

    /**
     * @var FolderLocation The base folder location.
     */
    private FolderLocation $location;

    /**
     * Constructs a Cache object.
     *
     * @param string         $path     Relative or absolute file path.
     * @param FolderLocation $location The base folder location.
     */
    public function __construct(string $path, FolderLocation $location)
    {
        $this->path = $path;
        $this->location = $location;
    }

    public function fullPath(string $type): string
    {
        switch ($type) {
            case 'body':
                return $this->basePath() . '/cache.body.txt';
            case 'headers':
                return $this->basePath() . '/cache.headers.json';
            default:
                throw new \InvalidArgumentException("Invalid cache type: $type");
        }
    }

    public function basePath(): string
    {
        return $this->location->basePath() . $this->path;
    }
}
