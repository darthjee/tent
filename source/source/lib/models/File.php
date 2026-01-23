<?php

namespace Tent\Models;

use Tent\Models\FolderLocation;
use Tent\Utils\ContentType;

/**
 * Represents a file within a folder location.
 *
 * Used to combine a file path with a base folder location, providing the full path to the file.
 */
class File
{
    /**
     * @var string Relative or absolute file path.
     */
    private string $path;

    /**
     * @var FolderLocation The base folder location.
     */
    private FolderLocation $location;

    private $content;

    /**
     * Constructs a File object.
     *
     * @param string         $path     Relative or absolute file path.
     * @param FolderLocation $location The base folder location.
     */
    public function __construct(string $path, FolderLocation $location)
    {
        $this->path = $path;
        $this->location = $location;
    }

    /**
     * Returns the full path to the file, combining the base folder and file path.
     *
     * @return string The full file path.
     */
    public function fullPath(): string
    {
        return $this->location->basePath() . $this->path;
    }

    /**
     * Returns the content of the file.
     *
     * @return string The file content.
     */
    public function content(): string
    {
        if ($this->content == null) {
            $this->content = file_get_contents($this->fullPath());
        }
        return $this->content;
    }

    /**
     * Returns the MIME content type of the file based on its extension.
     *
     * @see ContentType::getContentType()
     *
     * @return string The MIME content type.
     */
    public function contentType(): string
    {
        return ContentType::getContentType($this->fullPath());
    }

    /**
     * Returns the length of the file content in bytes.
     *
     * @return integer The content length in bytes.
     */
    public function contentLength(): int
    {
        return strlen($this->content());
    }
}
