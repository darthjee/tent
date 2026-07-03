<?php

namespace Tent\Middlewares;

use Tent\Content\CacheDirCleaner;
use Tent\Models\FolderLocation;
use Tent\Models\ProcessingRequest;
use Tent\Utils\PlaceholderPattern;

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
 * ## Custom targets
 *
 * `custom` maps a `:placeholder` route pattern to an explicit list of cache
 * path templates to clear when a mutating request matches it. This is
 * additive to `collection`/`entity` cleanup — both run for a matching
 * mutating request. See `PlaceholderPattern` for the supported placeholder
 * families.
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
 *             'custom'   => [
 *                 '/games/:game_slug/photo_upload' => [
 *                     '/games.json',
 *                     '/games/:game_slug.json',
 *                 ]
 *             ]
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

    /** @var array<string, string[]> */
    private array $customRules;

    private CacheDirCleaner $cleaner;

    /**
     * @param FolderLocation               $location     Base cache directory (must match FileCacheMiddleware).
     * @param string[]|null                $clearTargets Explicit targets, or null to use method-driven defaults.
     * @param array<string, string[]>|null $customRules  Map of route pattern to target path templates.
     */
    public function __construct(FolderLocation $location, ?array $clearTargets = null, ?array $customRules = null)
    {
        $this->location = $location;
        $this->clearTargets = $clearTargets;
        $this->customRules = $customRules ?? [];
        $this->cleaner = new CacheDirCleaner($location);
    }

    /**
     * Builds a CacheCleanupMiddleware from configuration attributes.
     *
     * @param array $attributes Must include `location`; optionally `clear`, `custom`.
     * @return CacheCleanupMiddleware
     */
    public static function build(array $attributes): CacheCleanupMiddleware
    {
        $location = new FolderLocation($attributes['location']);
        $clearTargets = isset($attributes['clear']) ? (array) $attributes['clear'] : null;
        $customRules = $attributes['custom'] ?? [];

        return new self($location, $clearTargets, $customRules);
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

        $this->cleanCustomTargets($path);

        return $request;
    }

    /**
     * Clears every configured `custom` target matching the given path.
     *
     * More than one `custom` pattern may match the same path; all matches
     * apply.
     *
     * @param string $path Request path.
     * @return void
     */
    private function cleanCustomTargets(string $path): void
    {
        foreach ($this->customRules as $pattern => $targetTemplates) {
            $values = PlaceholderPattern::match($pattern, $path);

            if ($values === null) {
                continue;
            }

            foreach ((array) $targetTemplates as $targetTemplate) {
                $target = PlaceholderPattern::substitute($targetTemplate, $values);
                $this->cleaner->cleanPath($target);
            }
        }
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
