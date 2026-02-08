<?php

namespace Tent\Content;

use Tent\Models\FolderLocation;
use Tent\Utils\ContentType;
use Tent\Content\ResponseContent;
use Tent\Utils\FileUtils;

/**
 * Represents a file within a folder location.
 *
 * Used to combine a file path with a base folder location, providing the full path to the file.
 */
class File implements ResponseContent
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
     * Returns HTTP headers for the file, including Content-Type and Content-Length.
     *
     * @see contentType()
     * @see contentLength()
     *
     * @return array Array of HTTP header strings.
     */
    public function headers(): array
    {
        return [
            "Content-Type: " . $this->contentType(),
            "Content-Length: " . $this->contentLength()
        ];
    }

    /**
     * Returns the HTTP status code for the file.
     *
     * @return integer The HTTP status code (always 200 for files).
     */
    public function httpCode(): int
    {
        return 200;
    }

    /**
     * Checks if the file exists and is a regular file.
     *
     * @return boolean True if the file exists and is a regular file, false otherwise.
     */
    public function exists(): bool
    {
        return FileUtils::exists($this->fullPath());
    }

    /**
     * Returns the full path to the file, combining the base folder and file path.
     *
     * @return string The full file path.
     */
    private function fullPath(): string
    {
        return FileUtils::getFullPath($this->location->basePath(), $this->path);
    }

    /**
     * Returns the MIME content type of the file based on its extension.
     *
     * @see ContentType::getContentType()
     *
     * @return string The MIME content type.
     */
    private function contentType(): string
    {
        return ContentType::getContentType($this->fullPath());
    }

    /**
     * Returns the length of the file content in bytes.
     *
     * @return integer The content length in bytes.
     */
    private function contentLength(): int
    {
        return strlen($this->content());
    }
}
