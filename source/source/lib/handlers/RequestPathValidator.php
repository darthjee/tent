<?php

namespace Tent\Handlers;

use Tent\Models\Request;

/**
 * Validates a request path string to prevent path traversal and other invalid patterns.
 *
 * Usage:
 *   $validator = new RequestPathValidator($path);
 *   if ($validator->isValid()) { ... }
 */
class RequestPathValidator
{
    /**
     * @var string The request path to validate.
     */
    private $path;

    /**
     * Constructs a RequestPathValidator for the given path.
     *
     * @param string $path The request path to validate.
     */
    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * Checks if the request path is valid (no path traversal).
     *
     * @return boolean True if valid, false if path traversal detected.
     */
    public function isValid(): bool
    {
        // Reject any path containing ".." or backslash
        if (strpos($this->path, '..') !== false || strpos($this->path, '\\') !== false) {
            return false;
        }
        // Paths starting with '/' are valid (e.g., /assets/file.js)
        return true;
    }
}
