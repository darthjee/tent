<?php

namespace Tent\Models;

use Tent\Models\FolderLocation;
use Tent\Models\ResponseContent;
use Tent\Utils\FilePath;

class FileCache implements ResponseContent
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

    public function content(): string
    {
        if ($this->content == null) {
            $this->content = file_get_contents($this->fullPath('body'));
        }
        return $this->content;
    }

    public function headers(): array
    {
        $headersPath = $this->fullPath('headers');
        $content = file_get_contents($headersPath);
        return json_decode($content, true);
    }

    public function exists(): bool
    {
        $bodyExists = file_exists($this->fullPath('body')) && is_file($this->fullPath('body'));
        $headersExists = file_exists($this->fullPath('headers')) && is_file($this->fullPath('headers'));
        
        return $bodyExists && $headersExists;
    }

    protected function fullPath(string $type): string
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

    protected function basePath(): string
    {
        return FilePath::getFullPath($this->path, $this->location);
    }
}
