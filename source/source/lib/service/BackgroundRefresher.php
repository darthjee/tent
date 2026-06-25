<?php

namespace Tent\Service;

use Tent\Content\FileCache;
use Tent\Http\HttpClientInterface;
use Tent\Log\Logger;
use Tent\Models\RequestInterface;
use Tent\Models\Response;

/**
 * Re-issues an upstream request and overwrites a stale `FileCache` entry with the fresh result.
 *
 * Used by `Tent\Middlewares\CacheStalenessMiddleware` to refresh a cache entry that has
 * already been served (stale) to the current client, without affecting that response.
 *
 * `run()` is fully synchronous; how/when it gets invoked (immediately or deferred via
 * `register_shutdown_function()`) is decided by the caller, keeping this class free of
 * any FPM/SAPI-specific concerns.
 *
 * ## Example
 *
 * ```php
 * $refresher = new BackgroundRefresher($request, $cache, 'http://api:80', new CurlHttpClient());
 * $refresher->run();
 * ```
 */
class BackgroundRefresher
{
    /**
     * @var RequestInterface The original request used to rebuild the upstream call.
     */
    private RequestInterface $request;

    /**
     * @var FileCache The cache entry to refresh.
     */
    private FileCache $cache;

    /**
     * @var string Base URL of the upstream server to re-contact.
     */
    private string $host;

    /**
     * @var HttpClientInterface The HTTP client used to re-issue the upstream request.
     */
    private HttpClientInterface $httpClient;

    /**
     * @param RequestInterface    $request    The request to replay against the upstream server.
     * @param FileCache           $cache      The cache entry to refresh.
     * @param string              $host       Base URL of the upstream server (e.g. 'http://api:80').
     * @param HttpClientInterface $httpClient The HTTP client used to perform the upstream request.
     */
    public function __construct(
        RequestInterface $request,
        FileCache $cache,
        string $host,
        HttpClientInterface $httpClient
    ) {
        $this->request = $request;
        $this->cache = $cache;
        $this->host = rtrim($host, '/');
        $this->httpClient = $httpClient;
    }

    /**
     * Entry point that logs the start of the refresh and delegates to `attemptRefresh()`.
     *
     * @return void
     */
    public function run(): void
    {
        $uri = $this->request->requestPath();
        Logger::debug('[refresh] - starting background cache refresh — uri: ' . $uri);

        $this->attemptRefresh($uri);
    }

    /**
     * Wraps the refresh attempt in a try/catch, delegating the actual work to
     * `refresh()` and any failure handling to `handleFailure()`.
     *
     * @param string $uri The request path being refreshed (used for logging).
     * @return void
     */
    private function attemptRefresh(string $uri): void
    {
        try {
            $this->refresh($uri);
        } catch (\Throwable $exception) {
            $this->handleFailure($uri, $exception);
        }
    }

    /**
     * Performs the upstream refresh request and re-stores the result into the cache.
     *
     * Removes the stale entry first since `ResponseCacher::process()` only stores into
     * a cache that does not already `exist()`.
     *
     * @param string $uri The request path being refreshed (used for logging).
     * @return void
     */
    private function refresh(string $uri): void
    {
        $response = $this->fetch();
        $this->cache->remove();
        (new ResponseCacher($this->cache, $response))->process();

        Logger::debug(
            '[' . $response->httpCode() . '] - background cache refresh completed — uri: ' . $uri
        );
    }

    /**
     * Logs a warning when the background refresh attempt fails.
     *
     * @param string     $uri       The request path being refreshed (used for logging).
     * @param \Throwable $exception The exception raised during the refresh attempt.
     * @return void
     */
    private function handleFailure(string $uri, \Throwable $exception): void
    {
        Logger::warn('[refresh] - background cache refresh failed — uri: ' . $uri .
            ', reason: ' . $exception->getMessage());
    }

    /**
     * Issues the upstream request and builds a Response from it.
     *
     * @return Response The fresh upstream response.
     */
    private function fetch(): Response
    {
        $url = $this->fullUrl();

        $rawResponse = $this->httpClient->request(
            $this->request->requestMethod(),
            $url,
            $this->request->headers(),
            $this->request->body(),
            $this->request->uploadedFiles(),
            $this->request->postFields()
        );
        $rawResponse['request'] = $this->request;

        return new Response($rawResponse);
    }

    /**
     * Builds the full upstream URL for the request being refreshed.
     *
     * @return string
     */
    private function fullUrl(): string
    {
        $path = '/' . ltrim($this->request->requestPath(), '/');
        $url = $this->host . $path;

        $query = $this->request->query();
        if ($query) {
            $url .= '?' . ltrim($query, '?');
        }

        return $url;
    }
}
