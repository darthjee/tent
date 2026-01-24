<?php

namespace Tent\Models;

use Tent\Models\FolderLocation;
use Tent\Models\ResponseContent;
use Tent\Utils\FileUtils;
use InvalidArgumentException;
use Tent\Models\Response;

class FileCache implements Cache
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

    /**
     * Returns the content of the cached response body.
     *
     * @return string The cached response body content.
     */
    public function content(): string
    {
        if ($this->content == null) {
            $this->content = file_get_contents($this->fullPath('body'));
        }
        return $this->content;
    }

    /**
     * Returns HTTP headers for the cached response.
     *
     * @return array Array of HTTP header strings.
     */
    public function headers(): array
    {
        $headersPath = $this->fullPath('headers');
        $content = file_get_contents($headersPath);
        return json_decode($content, true);
    }

    /**
     * Checks if the cached response files exist.
     *
     * @see FileUtils::exists()
     *
     * @return boolean True if both body and headers cache files exist, false otherwise.
     */
    public function exists(): bool
    {
        $bodyPath = $this->fullPath('body');
        $headersPath = $this->fullPath('headers');

        return FileUtils::exists($bodyPath) && FileUtils::exists($headersPath);
    }

    public function store(Response $response): void
    {
        mkdir($this->basePath(), 0777, true);
        file_put_contents($this->fullPath('body'), $response->body());
        file_put_contents($this->fullPath('headers'), json_encode($response->headerLines()));
    }

    /**
     * Returns the full path for the specified cache type.
     *
     * @param string $type The cache type ('body' or 'headers').
     * @return string The full path to the cache file.
     */
    protected function fullPath(string $type): string
    {
        switch ($type) {
            case 'body':
                return $this->basePath() . '/cache.body.txt';
            case 'headers':
                return $this->basePath() . '/cache.headers.json';
            default:
                throw new InvalidArgumentException("Invalid cache type: $type");
        }
    }

    /**
     * Returns the base path for the cache files.
     *
     * @return string The base path.
     */
    protected function basePath(): string
    {
        return FileUtils::getFullPath($this->path, $this->location);
    }
}
