<?php

namespace Tent\Middlewares;

use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;
use Tent\Utils\FileUtils;
use Tent\Log\Logger;

/**
 * Middleware that deletes stale file-cache directories on mutating requests.
 *
 * Activates on POST, PATCH, PUT, DELETE. Deletes the `GET/` subdirectories
 * inside the cache location that correspond to the affected REST resources,
 * ensuring `FileCacheMiddleware` cannot serve stale data after a write.
 *
 * ## Default cleanup targets
 *
 * | Method         | Targets                  |
 * |----------------|--------------------------|
 * | POST           | `collection`             |
 * | PATCH/PUT/DELETE | `collection` + `entity` |
 *
 * - `collection`: the parent-resource cache dir (`{location}/{parent}/GET/`)
 * - `entity`: the entity cache dir (`{location}/{path}/GET/`)
 *
 * ## Example configuration
 *
 * ```php
 * Configuration::buildRule([
 *     'handler'     => [...],
 *     'matchers'    => [...],
 *     'middlewares' => [
 *         [
 *             'class'    => 'Tent\\Middlewares\\CacheCleanupMiddleware',
 *             'location' => './cache',
 *             'clear'    => ['collection', 'entity'],
 *         ]
 *     ]
 * ]);
 * ```
 */
class CacheCleanupMiddleware extends Middleware
{
    private const MUTATING_METHODS = ['POST', 'PATCH', 'PUT', 'DELETE'];

    private const DEFAULT_TARGETS = [
        'POST'   => ['collection'],
        'PATCH'  => ['collection', 'entity'],
        'PUT'    => ['collection', 'entity'],
        'DELETE' => ['collection', 'entity'],
    ];

    private FolderLocation $location;

    /** @var string[]|null */
    private ?array $clearTargets;

    /**
     * @param FolderLocation $location     Base cache directory (must match FileCacheMiddleware).
     * @param string[]|null  $clearTargets Explicit targets, or null to use method-driven defaults.
     */
    public function __construct(FolderLocation $location, ?array $clearTargets = null)
    {
        $this->location = $location;
        $this->clearTargets = $clearTargets;
    }

    /**
     * Builds a CacheCleanupMiddleware from configuration attributes.
     *
     * @param array $attributes Must include `location`; optionally `clear`.
     * @return CacheCleanupMiddleware
     */
    public static function build(array $attributes): CacheCleanupMiddleware
    {
        $location = new FolderLocation($attributes['location']);
        $clearTargets = isset($attributes['clear']) ? (array) $attributes['clear'] : null;

        return new self($location, $clearTargets);
    }

    /**
     * Deletes stale cache directories before the request is forwarded upstream.
     *
     * @param ProcessingRequest $request The incoming request.
     * @return ProcessingRequest
     */
    public function processRequest(ProcessingRequest $request): ProcessingRequest
    {
        $method = strtoupper($request->requestMethod());

        if (!in_array($method, self::MUTATING_METHODS, true)) {
            return $request;
        }

        $targets = $this->clearTargets ?? self::DEFAULT_TARGETS[$method];
        $path = $request->requestPath();

        foreach ($targets as $target) {
            $dir = $this->resolveDir($target, $path);
            if ($dir !== null) {
                $this->deleteDir($dir);
            }
        }

        return $request;
    }

    /**
     * Resolves the cache directory for a given target and request path.
     *
     * Returns null when the target cannot be meaningfully applied
     * (e.g. `entity` on a single-segment path).
     *
     * @param string $target Target type, either 'collection' or 'entity'.
     * @param string $path   Request path, e.g. '/users/1'.
     * @return string|null
     */
    private function resolveDir(string $target, string $path): ?string
    {
        $base = $this->location->basePath();
        $segments = array_values(array_filter(explode('/', $path)));

        if ($target === 'collection') {
            $collectionSegments = count($segments) > 1 ? array_slice($segments, 0, -1) : $segments;
            $collectionPath = implode('/', $collectionSegments);
            return FileUtils::getFullPath($base, $collectionPath, 'GET');
        }

        if ($target === 'entity') {
            if (count($segments) < 2) {
                return null;
            }
            return FileUtils::getFullPath($base, implode('/', $segments), 'GET');
        }

        return null;
    }

    /**
     * Recursively deletes a directory and all its contents if it exists and is
     * safely scoped under the configured cache location.
     *
     * @param string $dir Absolute path to the directory to delete.
     * @return void
     */
    private function deleteDir(string $dir): void
    {
        $base = rtrim($this->location->basePath(), '/');
        if (!str_starts_with($dir, $base . '/')) {
            return;
        }

        if (!is_dir($dir)) {
            return;
        }

        $this->removeDirRecursive($dir);
        Logger::debug('cache cleared — dir: ' . $dir);
    }

    /**
     * Recursively removes a directory and all its contents.
     *
     * @param string $dir Absolute path to the directory to remove.
     * @return void
     */
    private function removeDirRecursive(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isFile() || $item->isLink()) {
                unlink($item->getPathname());
            } elseif ($item->isDir()) {
                rmdir($item->getPathname());
            }
        }
        rmdir($dir);
    }
}
