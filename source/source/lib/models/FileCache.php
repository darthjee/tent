<?php

namespace Tent\Models;

use Tent\Models\FolderLocation;
use Tent\Models\ResponseContent;
use Tent\Utils\FileUtils;
use InvalidArgumentException;
use Tent\Models\Response;
use Tent\Utils\CacheFilePath;

class FileCache implements Cache
{
    /**
     * @var RequestInterface The request associated with this cache.
     */
    private RequestInterface $request;

    /**
     * @var string Relative or absolute file path.
     */
    private string $path;

    /**
     * @var FolderLocation The base folder location.
     */
    private FolderLocation $location;

    /**
     * @var string|null Cached content of the response body.
     */
    private ?string $content = null;

    /**
     * @var string Full path to the body cache file.
     */
    private string $bodyFilePath;

    /**
     * @var string Full path to the meta cache file.
     */
    private string $metaFilePath;

    /**
     * Constructs a Cache object.
     *
     * @param RequestInterface $request  The request associated with this cache.
     * @param FolderLocation   $location The base folder location.
     */
    public function __construct(RequestInterface $request, FolderLocation $location)
    {
        $this->request = $request;
        $this->path = $request->requestPath();
        $this->location = $location;

        $query = $this->request->query();
        $this->bodyFilePath = CacheFilePath::path('body', $this->basePath(), $query);
        $this->metaFilePath = CacheFilePath::path('meta', $this->basePath(), $query);
    }

    /**
     * Returns the content of the cached response body.
     *
     * @return string The cached response body content.
     */
    public function content(): string
    {
        if ($this->content == null) {
            $this->content = file_get_contents($this->bodyFilePath);
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
        $content = file_get_contents($this->metaFilePath);
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
        return FileUtils::exists($this->bodyFilePath) && FileUtils::exists($this->metaFilePath);
    }

    /**
     * Stores the response body and headers into cache files.
     *
     * @param Response $response The response to cache.
     * @return void
     */
    public function store(Response $response): void
    {
        $basePath = $this->basePath();
        $this->ensureCacheFolderExists($basePath);
        file_put_contents($this->bodyFilePath, $response->body());
        file_put_contents($this->metaFilePath, json_encode($response->headerLines()));
    }

    /**
     * Returns the full path for the specified cache type.
     *
     * @param string $type The cache type ('body' or 'meta').
     * @return string The full path to the cache file.
     */
    protected function fullPath(string $type): string
    {
        return CacheFilePath::path($type, $this->basePath(), $this->request->query());
    }

    /**
     * Returns the base path for the cache files.
     *
     * @return string The base path.
     */
    protected function basePath(): string
    {
        return FileUtils::getFullPath($this->location->basePath(), $this->path);
    }

    /**
     * Ensures the cache folder exists, creating it if necessary.
     *
     * @param string $basePath The path to the cache folder.
     * @return void
     */
    protected function ensureCacheFolderExists(string $basePath): void
    {
        if (!is_dir($basePath)) {
            mkdir($basePath, 0777, true);
        }
    }
}
