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
     * @var string Cached hash of the request query.
     */
    private string $queryHash;

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
        $this->queryHash = hash('sha256', $request->query() ?? '');
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
        return CacheFilePath::path($type, $this->request, $this->location->basePath());
    }

    /**
     * Returns the base path for the cache files.
     *
     * @return string The base path.
     */
    protected function basePath(): string
    {
        return FileUtils::getFullPath([$this->location->basePath(), $this->path]);
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
