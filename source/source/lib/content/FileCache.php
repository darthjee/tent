<?php

namespace Tent\Content;

use Tent\Models\FolderLocation;
use Tent\Log\Logger;
use Tent\Utils\FileUtils;
use Tent\Models\Response;
use Tent\Utils\CacheFilePath;
use Tent\Utils\SafeRemover;
use Tent\Models\ProcessingRequest;
use Tent\Cache\RequestHasher;
use Tent\Cache\QueryRequestHasher;

/**
 * File-based cache implementation for Tent.
 *
 * FileCache stores and retrieves HTTP response bodies and metadata (headers, status code)
 * in the filesystem, using a folder structure and hashed paths based on the request.
 *
 * Implements the Cache interface, so it can both read cached content (as ResponseContent)
 * and store new responses (via store()).
 *
 * - Body is stored in a file (body cache).
 * - Metadata (headers, httpCode) is stored in a separate file (meta cache).
 * - Paths are generated using CacheFilePath utilities.
 *
 * Used by FileCacheMiddleware to provide persistent caching for proxy/static responses.
 *
 * ## Uncacheable entries
 *
 * An entry is inert when either:
 *
 * - its resolved directory lies inside `<location>/.tent-trash`, which is
 *   reserved for directories being invalidated (see {@see CacheTrash});
 * - its request path or method has a `.` or `..` segment, or a NUL byte (see
 *   {@see FileUtils::hasSafeSegments()}), as it could resolve outside the cache
 *   location. A `warn` is logged in this case;
 * - the filesystem cannot hold it, as it is overlong (see below). A `warn` is
 *   logged in this case.
 *
 * An inert entry is uncacheable: it is never reported as a hit, nothing is read,
 * {@see store()} writes nothing and creates no directory, and {@see remove()}
 * does nothing. The request itself is not affected, so it is processed (e.g.
 * proxied) as usual.
 *
 * These are the only path screenings applied to cache hits, which are read
 * through {@see \Tent\Service\CacheContentReader}. Safe look-alikes, such as
 * `...`, `..foo`, `a..b` or names with a backslash, are cacheable.
 *
 * ## Overlong entries
 *
 * Lengths are measured in bytes, on the exact strings built for the filesystem
 * (the request path is not decoded, and duplicate slashes count), against the
 * limits of 255 bytes per name and 4096 bytes per path (the latter including the
 * terminating NUL). An entry is overlong when either:
 *
 * - a segment of the request path, or the method, is longer than 255 bytes;
 * - the name of its body or metadata file is longer than 255 bytes, e.g. with a
 *   custom {@see RequestHasher} returning a long hash;
 * - the path of its body or metadata file is longer than 4095 bytes. The cache
 *   location counts here, but its segments are not checked one by one.
 *
 * Both file checks keep a headroom for the files derived from those two, the
 * longest being the temporary file of {@see FileUtils::writeAtomically()}. Any
 * longer suffix appended to a cache file path requires a larger headroom.
 *
 * The check is lexical, so the filesystem is not touched. A single `warn` is
 * logged, with the reason `path too long` and the path (and method) truncated. A
 * request which is also unsafe is only reported as such.
 *
 * Entries written without that headroom by earlier versions, which wrote files
 * directly, are therefore permanent misses: they are neither stored again nor
 * removed by {@see remove()}, and their files stay until their directory is
 * invalidated (see {@see CacheDirCleaner}) or manually purged.
 *
 * ## Symbolic links and permissions
 *
 * Writes are contained in the cache location and never follow symbolic links out
 * of it: an entry whose directory resolves outside of it is not stored (with a
 * `warn`), and a link standing in place of a cache file is replaced. Directories
 * are created with mode `0755` at most, and files are set to `0644`, whatever
 * the umask. See {@see store()}.
 *
 * Reads still trust the cache directory: symbolic links are not checked on cache
 * hits, so the directory must be writable only by tent.
 *
 * ## Example: Direct usage
 *
 * ```php
 * $request = ...; // ProcessingRequest instance
 * $location = new FolderLocation('/tmp/cache');
 * $cache = new FileCache($request, $location);
 * // Optionally: new FileCache($request, $location, new CustomRequestHasher());
 *
 * // Reading from cache
 * if ($cache->exists()) {
 *     $body = $cache->content();
 *     $headers = $cache->headers();
 *     $code = $cache->httpCode();
 * }
 *
 * // Storing a response
 * $response = ...; // Response instance
 * $cache->store($response);
 * ```
 *
 * ## Example: Configuration (via FileCacheMiddleware)
 *
 * ```php
 * Configuration::buildRule([
 *     'handler' => [
 *         'type' => 'proxy',
 *         'host' => 'http://api:80'
 *     ],
 *     'matchers' => [
 *         ['method' => 'GET', 'uri' => '/persons', 'type' => 'exact']
 *     ],
 *     'middlewares' => [
 *         [
 *             'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
 *             'location' => './cache',
 *             'httpCodes' => [200]
 *         ]
 *     ]
 * ]);
 * ```
 */
class FileCache implements Cache
{
    /**
     * Longest file or directory name the filesystem holds, in bytes.
     */
    private const NAME_MAX = 255;

    /**
     * Size of the longest path the filesystem accepts, in bytes, including its
     * terminating NUL.
     */
    private const PATH_MAX = 4096;

    /**
     * Bytes kept free for the suffix of any file derived from a cache file. It must
     * be at least as long as every suffix appended to a body or meta file path.
     */
    private const DERIVED_SUFFIX_HEADROOM = FileUtils::TEMPORARY_SUFFIX_LENGTH;

    /**
     * Longest request path, or method, logged in full for an overlong entry, in bytes.
     */
    private const LOGGED_VALUE_MAX_LENGTH = 200;

    /**
     * @var ProcessingRequest The request associated with this cache.
     */
    private ProcessingRequest $request;

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
     * @var string The cache-key hash used to derive the body/meta file paths.
     */
    private string $hash;

    /**
     * @var boolean Whether the entry is uncacheable: it resolves inside the reserved
     *              trash folder, or its request path or method is unsafe or overlong.
     */
    private bool $inert;

    /**
     * Constructs a Cache object.
     *
     * @param ProcessingRequest  $request       The request associated with this cache.
     * @param FolderLocation     $location      The base folder location.
     * @param RequestHasher|null $requestHasher Optional hasher used to derive the cache key.
     *                                          Defaults to {@see QueryRequestHasher}. The
     *                                          resulting hash is memoized on `$request` so it
     *                                          is computed at most once per request.
     */
    public function __construct(
        ProcessingRequest $request,
        FolderLocation $location,
        ?RequestHasher $requestHasher = null
    ) {
        $this->request = $request;
        $this->path = $request->requestPath();
        $this->location = $location;

        $this->hash = $request->cacheHash() ?? $request->setCacheHash(
            ($requestHasher ?? new QueryRequestHasher())->hash($request)
        );

        $this->bodyFilePath = CacheFilePath::path('body', $this->basePath(), $this->hash);
        $this->metaFilePath = CacheFilePath::path('meta', $this->basePath(), $this->hash);

        // Short-circuited, so that an entry is reported with a single reason.
        $this->inert = $this->hasUnsafePath()
            || $this->hasOverlongPath()
            || (new CacheTrash($location))->contains($this->basePath());
    }

    /**
     * Returns the content of the cached response body.
     *
     * Always empty for an inert entry.
     *
     * @return string The cached response body content.
     */
    public function content(): string
    {
        if ($this->inert) {
            return '';
        }

        if ($this->content == null) {
            $this->content = file_get_contents($this->bodyFilePath);
        }
        return $this->content;
    }

    /**
     * Returns HTTP headers for the cached response.
     *
     * Includes an `X-CACHE-TIME` header derived from the stored `timestamp`
     * when the timestamp is present in the metadata.
     *
     * @return array Array of HTTP header strings.
     */
    public function headers(): array
    {
        $meta = $this->readMeta();
        $headers = $meta['headers'] ?? [];

        if (isset($meta['timestamp'])) {
            $headers[] = 'X-Cache-Time: ' . gmdate('Y-m-d H:i:s', $meta['timestamp']);
        }

        return $headers;
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
     * Returns the raw Unix timestamp stored when this entry was last written.
     *
     * Returns null when the entry has no metadata (e.g. cache miss), the
     * metadata does not include a `timestamp` field, or the entry is inert.
     *
     * @return integer|null The stored Unix timestamp, or null when unavailable.
     */
    public function timestamp(): ?int
    {
        if ($this->inert) {
            return null;
        }

        $meta = $this->readMeta();
        return $meta['timestamp'] ?? null;
    }

    /**
     * Returns the full path to the metadata cache file.
     *
     * Exposed so callers (e.g. staleness/debounce mechanisms) can derive
     * sentinel/lock file paths scoped to this specific cache entry.
     *
     * @return string The full path to the meta cache file.
     */
    public function metaFilePath(): string
    {
        return $this->metaFilePath;
    }

    /**
     * Checks if the cached response files exist.
     *
     * Always false for an inert entry.
     *
     * @see FileUtils::exists()
     *
     * @return boolean True if both body and metadata cache files exist, false otherwise.
     */
    public function exists(): bool
    {
        if ($this->inert) {
            return false;
        }

        return FileUtils::exists($this->bodyFilePath) && FileUtils::exists($this->metaFilePath);
    }

    /**
     * Stores the response body and metadata into cache files.
     *
     * Nothing is created nor written for an inert entry.
     *
     * Nothing is created nor written either, and a `warn` is logged, when the
     * entry directory, or its deepest existing ancestor, resolves outside the
     * cache location (see {@see FolderLocation::contains()}), e.g. through a
     * symbolic link. This is checked before the directory is created, and again
     * afterwards. The cache location may itself be a link, and is created when
     * missing.
     *
     * A directory which cannot be created (e.g. permission denied, full or
     * read-only disk, lower filesystem limits) is logged at `warn` level, and
     * nothing is written. No PHP warning is raised.
     *
     * Each file is written atomically with mode `0644` (see
     * {@see FileUtils::writeAtomically()}), so that a symbolic link standing in
     * its place is replaced, never followed. The body is written first and the
     * metadata last: as {@see exists()} requires both, a new entry is only
     * reported once complete. The metadata is not written when the body cannot
     * be.
     *
     * Known limitation (TOCTOU): a directory swapped for a symbolic link between
     * these checks and the creation or the writes is not prevented; checking
     * again after the creation only narrows that window. PHP offers no
     * `openat()`-style traversal to close it, and exploiting it requires write
     * access inside the cache directory.
     *
     * @param Response $response The response to cache.
     * @return void
     */
    public function store(Response $response): void
    {
        if ($this->inert || !$this->prepareCacheFolder()) {
            return;
        }

        if (FileUtils::writeAtomically($this->bodyFilePath, $response->body())) {
            FileUtils::writeAtomically($this->metaFilePath, (string) json_encode($this->buildMeta($response)));
        }
    }

    /**
     * Removes the cached body and metadata files, if present.
     *
     * Used to discard a stale entry before re-storing a fresh response,
     * since {@see store()} only writes the entry and {@see ResponseCacher}
     * (used by background refreshers) only stores into a cache that does
     * not already `exist()`.
     *
     * Files that are missing, or removed concurrently by another writer, are
     * tolerated silently. Real removal failures are logged at `warn` level by
     * {@see SafeRemover} and are never thrown.
     *
     * Nothing is removed for an inert entry.
     *
     * @return void
     */
    public function remove(): void
    {
        if ($this->inert) {
            return;
        }

        $remover = new SafeRemover();
        $remover->removeFile($this->bodyFilePath);
        $remover->removeFile($this->metaFilePath);

        $this->content = null;
    }

    /**
     * Reads and decodes the metadata file.
     *
     * Returns an empty array if the file doesn't exist, is not readable,
     * if JSON decoding fails, or if the entry is inert.
     *
     * @return array The decoded metadata array, or empty array on failure.
     */
    protected function readMeta(): array
    {
        if ($this->inert || !is_readable($this->metaFilePath)) {
            return [];
        }

        $content = file_get_contents($this->metaFilePath);
        if ($content === false) {
            return [];
        }

        $meta = json_decode($content, true);
        if (!is_array($meta)) {
            return [];
        }

        return $meta;
    }

    /**
     * Builds the metadata array for the cached response.
     *
     * Includes a `timestamp` field with the current Unix timestamp.
     *
     * @param Response $response The response to build metadata from.
     * @return array The metadata array.
     */
    protected function buildMeta(Response $response): array
    {
        return [
            'headers' => $response->headers(),
            'httpCode' => $response->httpCode(),
            'timestamp' => time()
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
        return CacheFilePath::path($type, $this->basePath(), $this->hash);
    }

    /**
     * Returns the base path for the cache files, including the HTTP method.
     *
     * @return string The base path.
     */
    protected function basePath(): string
    {
        $pathWithMethod = FileUtils::getFullPath($this->path, $this->request->requestMethod());
        return FileUtils::getFullPath($this->location->basePath(), $pathWithMethod);
    }

    /**
     * Ensures the cache folder exists, creating it if necessary.
     *
     * Directories are created with mode `0755`, which the umask may only reduce.
     *
     * A failed creation never raises a PHP warning, which could be printed into
     * the response: it is logged at `warn` level instead, along with the reason.
     *
     * @return boolean True when the folder exists.
     */
    protected function ensureCacheFolderExists(): bool
    {
        $basePath = $this->basePath();
        if (is_dir($basePath)) {
            return true;
        }

        // So that an earlier error is never reported as the reason.
        error_clear_last();

        // The folder may have been created meanwhile by a concurrent request.
        if (@mkdir($basePath, 0755, true) || is_dir($basePath)) {
            return true;
        }

        Logger::warn(
            'cache store skipped — path: ' . $this->path . ', method: ' . $this->request->requestMethod() .
            ', reason: ' . (error_get_last()['message'] ?? 'mkdir failed')
        );

        return false;
    }

    /**
     * Creates the entry directory, making sure that nothing is created, nor going
     * to be written, outside the cache location.
     *
     * @return boolean True when the entry can be written into its directory.
     */
    private function prepareCacheFolder(): bool
    {
        $basePath = $this->basePath();
        $ancestor = $this->deepestExistingAncestor($basePath);

        // Only a cache location yet to be created is missing here, holding no link.
        if (!$this->isMissing($ancestor) && !$this->isContained($ancestor)) {
            return false;
        }

        // A failed creation has already been logged.
        return $this->ensureCacheFolderExists() && is_dir($basePath) && $this->isContained($basePath);
    }

    /**
     * Walks up a path until something exists, without going above the cache location.
     *
     * A symbolic link counts as existing even when its target does not, so that
     * it is checked rather than skipped over.
     *
     * @param string $path Path under the cache location.
     * @return string The deepest existing ancestor (possibly the path itself), or
     *                the cache location when nothing exists up to it.
     */
    private function deepestExistingAncestor(string $path): string
    {
        $root = rtrim($this->location->basePath(), '/');

        while ($path !== $root && $this->isMissing($path)) {
            $path = dirname($path);
        }

        return $path;
    }

    /**
     * Checks that nothing, not even a dangling symbolic link, stands at a path.
     *
     * @param string $path Path to check.
     * @return boolean True when the path is free.
     */
    private function isMissing(string $path): bool
    {
        return !file_exists($path) && !is_link($path);
    }

    /**
     * Checks that a path resolves inside the cache location, logging a `warn`
     * about the skipped store otherwise.
     *
     * @param string $path Existing path, used to store the entry.
     * @return boolean False when the entry must not be stored.
     */
    private function isContained(string $path): bool
    {
        if ($this->location->contains($path)) {
            return true;
        }

        Logger::warn(
            'cache store skipped — path: ' . $this->path . ', method: ' . $this->request->requestMethod() .
            ', reason: resolves outside cache location'
        );

        return false;
    }

    /**
     * Checks whether the request path or method could lead outside the cache
     * location, logging a `warn` when it does.
     *
     * The method is checked too, as it is the last segment of the entry directory.
     *
     * @return boolean True when the entry must not touch the filesystem.
     */
    private function hasUnsafePath(): bool
    {
        $method = $this->request->requestMethod();

        if (FileUtils::hasSafeSegments($this->path . '/' . $method)) {
            return false;
        }

        Logger::warn('cache bypassed — path: ' . $this->path . ', method: ' . $method . ', reason: unsafe path');

        return true;
    }

    /**
     * Checks whether the filesystem cannot hold the entry, logging a `warn` when so.
     *
     * @return boolean True when the entry must not touch the filesystem.
     */
    private function hasOverlongPath(): bool
    {
        if (!$this->hasOverlongName() && !$this->hasOverlongFilePath()) {
            return false;
        }

        Logger::warn(
            'cache bypassed — path: ' . $this->truncateForLog($this->path) .
            ', method: ' . $this->truncateForLog($this->request->requestMethod()) .
            ', reason: path too long'
        );

        return true;
    }

    /**
     * Checks for a name exceeding `NAME_MAX`: a segment of the request path, the
     * method, or a cache file name, along with its derived suffix.
     *
     * The segments of the cache location are not checked.
     *
     * @return boolean True when any name is overlong.
     */
    private function hasOverlongName(): bool
    {
        $segments = explode('/', $this->path . '/' . $this->request->requestMethod());

        // Measured without basename(), which depends on the locale.
        $fileNameLength = $this->longestFilePathLength() - strlen($this->basePath() . '/');

        return max(array_map('strlen', $segments)) > self::NAME_MAX
            || $fileNameLength + self::DERIVED_SUFFIX_HEADROOM > self::NAME_MAX;
    }

    /**
     * Checks for a cache file path which, along with its derived suffix and the
     * terminating NUL, exceeds `PATH_MAX`.
     *
     * @return boolean True when the path is overlong.
     */
    private function hasOverlongFilePath(): bool
    {
        return $this->longestFilePathLength() + self::DERIVED_SUFFIX_HEADROOM > self::PATH_MAX - 1;
    }

    /**
     * Returns the length, in bytes, of the longest cache file path of the entry.
     *
     * @return integer The length of the longest of the body and meta file paths.
     */
    private function longestFilePathLength(): int
    {
        return max(strlen($this->bodyFilePath), strlen($this->metaFilePath));
    }

    /**
     * Shortens a client-controlled value, so that it cannot bloat the logs.
     *
     * @param string $value Value to be logged.
     * @return string The value itself or, when too long, its first bytes followed by its size.
     */
    private function truncateForLog(string $value): string
    {
        if (strlen($value) <= self::LOGGED_VALUE_MAX_LENGTH) {
            return $value;
        }

        return substr($value, 0, self::LOGGED_VALUE_MAX_LENGTH) . '… (' . strlen($value) . ' bytes)';
    }
}
