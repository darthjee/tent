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
    private $baseUrl;

    /**
     * Constructs a Server model.
     *
     * @param string $baseUrl The base address for proxy requests.
     */
    public function __construct(string $baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * Returns the base address (host) for proxy requests.
     *
     * @return string
     */
    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function host(): string
    {
        $parsedUrl = parse_url($this->baseUrl);
        $host = $parsedUrl['host'] ?? '';

        if (isset($parsedUrl['port'])) {
            return $host . ':' . $parsedUrl['port'];
        }

        return $host;
    }

    /**
     * Builds a full URL by combining the base URL with a given path and optional query string.
     * Ensures that slashes are properly handled to avoid malformed URLs.
     * Example:
     *   If baseUrl is 'http://api.example.com' and path is '/persons',
     *   the result will be 'http://api.example.com/persons'.
     *
     * @param string      $path  The path to append to the base URL.
     * @param string|null $query Optional query string to append to the URL.
     * @return string The full URL combining the base URL, path, and query string.
     */
    public function fullUrl(string $path, ?string $query = null): string
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        if ($query) {
            $url .= '?' . ltrim($query, '?');
        }
        return $url;
    }
}
