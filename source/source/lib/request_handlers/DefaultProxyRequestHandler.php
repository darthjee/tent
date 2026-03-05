<?php

namespace Tent\RequestHandlers;

use Tent\Http\HttpClientInterface;
use Tent\Middlewares\RenameHeaderMiddleware;
use Tent\Middlewares\SetHeadersMiddleware;
use Tent\Middlewares\FileCacheMiddleware;
use Tent\Models\FolderLocation;
use Tent\Matchers\StatusCodeMatcher;

/**
 * A ProxyRequestHandler with a default middleware stack for common proxy behavior.
 *
 * Automatically configures:
 * 1. RenameHeaderMiddleware: renames `Host` to `X-Forwarded-Host`.
 * 2. SetHeadersMiddleware: sets `Host` to the provided host value.
 * 3. FileCacheMiddleware (optional): caches responses matching the given HTTP codes.
 *
 * ## Usage Example
 *
 * ```php
 * // With caching enabled (default):
 * $handler = new DefaultProxyRequestHandler('http://api:80');
 *
 * // With a custom cache directory and codes:
 * $handler = new DefaultProxyRequestHandler('http://api:80', './my-cache', ['2xx']);
 *
 * // With caching disabled:
 * $handler = new DefaultProxyRequestHandler('http://api:80', false);
 * ```
 */
class DefaultProxyRequestHandler extends ProxyRequestHandler
{
    /**
     * @var string|false Cache directory or false to disable caching
     */
    private string|false $cache;
    /**
     * @var array HTTP status codes eligible for caching
     */
    private array $cacheCodes;

    /**
     * Constructs a DefaultProxyRequestHandler.
     *
     * @param string                   $host       The target host to proxy requests to.
     * @param string|false             $cache      Cache directory, or false to disable caching. Defaults to './cache'.
     * @param array                    $cacheCodes HTTP status codes eligible for caching. Defaults to ['2xx'].
     * @param HttpClientInterface|null $httpClient Optional HTTP client.
     */
    public function __construct(
        string $host,
        string|false $cache,
        array $cacheCodes,
        ?HttpClientInterface $httpClient = null
    ) {
        parent::__construct($host, $httpClient);
        $this->host = $host;
        $this->cache = $cache;
        $this->cacheCodes = $cacheCodes;
        $this->initializeMiddlewares();
    }

    /**
     * Builds a DefaultProxyRequestHandler from an associative array of parameters.
     *
     * @param array $params Associative array with keys:
     *   - 'host' (string, required): Target host URL.
     *   - 'cache' (string|false): Cache directory or false to disable. Defaults to './cache'.
     *   - 'cacheCodes' (array): HTTP codes to cache. Defaults to ['2xx'].
     * @return self
     * @throws \InvalidArgumentException If 'host' is missing.
     */
    public static function build(array $params): self
    {
        if (!isset($params['host'])) {
            throw new \InvalidArgumentException("Missing required parameter 'host'");
        }
        $host = $params['host'];
        $cache = array_key_exists('cache', $params) ? $params['cache'] : './cache';
        $cacheCodes = $params['cacheCodes'] ?? ['2xx'];
        return new self($host, $cache, $cacheCodes);
    }

    /**
     * Initializes the middleware stack in the correct order.
     * @return void
     */
    private function initializeMiddlewares(): void
    {
        $this->addMiddleware(new RenameHeaderMiddleware('Host', 'X-Forwarded-Host'));
        $this->addMiddleware(new SetHeadersMiddleware(['Host' => $this->host]));

        if ($this->cache !== false) {
            $this->addMiddleware(new FileCacheMiddleware(
                new FolderLocation($this->cache),
                [new StatusCodeMatcher($this->cacheCodes)]
            ));
        }
    }
}
