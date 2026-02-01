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
        $meta = $this->readMeta();
        return $meta['headers'] ?? [];
    }

    /**
     * Returns the HTTP status code for the cached response.
     *
     * Returns 200 if not found.
     *
     * @return integer The HTTP status code.
     */
    public function httpCode(): int
    {
        $meta = $this->readMeta();
        return $meta['httpCode'] ?? 200;
    }

    /**
     * Checks if the cached response files exist.
     *
     * @see FileUtils::exists()
     *
     * @return boolean True if both body and metadata cache files exist, false otherwise.
     */
    public function exists(): bool
    {
        return FileUtils::exists($this->bodyFilePath) && FileUtils::exists($this->metaFilePath);
    }

    /**
     * Stores the response body and metadata into cache files.
     *
     * @param Response $response The response to cache.
     * @return void
     */
    public function store(Response $response): void
    {
        $this->ensureCacheFolderExists();
        file_put_contents($this->bodyFilePath, $response->body());
        file_put_contents($this->metaFilePath, json_encode($this->buildMeta($response)));
    }

    /**
     * Reads and decodes the metadata file.
     *
     * @return array The decoded metadata array, or empty array on failure.
     */
    protected function readMeta(): array
    {
        $content = @file_get_contents($this->metaFilePath);
        return json_decode($content, true);
    }

    /**
     * Builds the metadata array for the cached response.
     *
     * @param Response $response The response to build metadata from.
     * @return array The metadata array.
     */
    protected function buildMeta(Response $response): array
    {
        return [
            'headers' => $response->headerLines(),
            'httpCode' => $response->httpCode()
        ];
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
     * @return void
     */
    protected function ensureCacheFolderExists(): void
    {
        $basePath = $this->basePath();
        if (!is_dir($basePath)) {
            mkdir($basePath, 0777, true);
        }
    }
}
