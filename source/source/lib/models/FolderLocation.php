<?php

namespace Tent\Models;

/**
 * Represents a base directory location for serving static files.
 *
 * Used by StaticFileHandler to determine where to look for files to serve.
 */
class FolderLocation
{
    private $basePath;

    /**
     * @param string $basePath The base directory path for static files.
     */
    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * Returns the base directory path.
     *
     * @return string The base path for static files.
     */
    public function basePath()
    {
        return $this->basePath;
    }
}
