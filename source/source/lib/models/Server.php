<?php

namespace Tent\Models;

/**
 * Model representing the base address for proxy requests.
 *
 * Used by ProxyHandler to define the target host for forwarding requests.
 * Similar to FolderLocation, this is a simple value object.
 */
class Server
{
    /**
     * @var string The base address (host) for proxy requests.
     */
    private $targetHost;

    /**
     * Constructs a Server model.
     *
     * @param string $targetHost The base address for proxy requests.
     */
    public function __construct(string $targetHost)
    {
        $this->targetHost = $targetHost;
    }

    /**
     * Returns the base address (host) for proxy requests.
     *
     * @return string
     */
    public function targetHost(): string
    {
        return $this->targetHost;
    }

    /**
     * Builds a full URL by combining the target host with a given path and optional query string.
     * Ensures that slashes are properly handled to avoid malformed URLs.
     * Example:
     *   If targetHost is 'http://api.example.com' and path is '/persons',
     *   the result will be 'http://api.example.com/persons'.
     *
     * @param string      $path  The path to append to the target host.
     * @param string|null $query Optional query string to append to the URL.
     * @return string The full URL combining the target host, path, and query string.
     */
    public function fullUrl(string $path, ?string $query = null): string
    {
        $url = rtrim($this->targetHost, '/') . '/' . ltrim($path, '/');
        if ($query) {
            $url .= '?' . ltrim($query, '?');
        }
        return $url;
    }
}
