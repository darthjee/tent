<?php

namespace Tent\Middlewares;

use Tent\Content\CacheDirCleaner;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;

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

    private CacheDirCleaner $cleaner;

    /**
     * @param FolderLocation $location     Base cache directory (must match FileCacheMiddleware).
     * @param string[]|null  $clearTargets Explicit targets, or null to use method-driven defaults.
     */
    public function __construct(FolderLocation $location, ?array $clearTargets = null)
    {
        $this->location = $location;
        $this->clearTargets = $clearTargets;
        $this->cleaner = new CacheDirCleaner($location);
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
        $targets = $this->resolveTargets($request);

        if ($targets === null) {
            return $request;
        }

        $path = $request->requestPath();

        foreach ($targets as $target) {
            $this->cleaner->clean($target, $path);
        }

        return $request;
    }

    /**
     * Resolves the cleanup targets that apply to the given request.
     *
     * Returns null when the request method is not mutating, meaning no
     * cleanup should be performed.
     *
     * @param ProcessingRequest $request The incoming request.
     * @return string[]|null
     */
    private function resolveTargets(ProcessingRequest $request): ?array
    {
        $method = strtoupper($request->requestMethod());

        if (!in_array($method, self::MUTATING_METHODS, true)) {
            return null;
        }

        return $this->clearTargets ?? self::DEFAULT_TARGETS[$method];
    }
}
