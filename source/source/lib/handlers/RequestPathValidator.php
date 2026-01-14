<?php

namespace Tent;

/**
 * Validates the request path to prevent path traversal and other invalid patterns.
 *
 * Usage:
 *   $validator = new RequestPathValidator($request);
 *   if ($validator->isValid()) { ... }
 */
class RequestPathValidator
{
    /**
     * @var Request The request to validate.
     */
    private $request;

    /**
     * Constructs a RequestPathValidator for the given request.
     *
     * @param Request $request The HTTP request to validate.
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Checks if the request path is valid (no path traversal).
     *
     * @return boolean True if valid, false if path traversal detected.
     */
    public function isValid(): bool
    {
        $path = $this->request->requestUrl();
        // Reject any path containing ".." or backslash
        if (strpos($path, '..') !== false || strpos($path, '\\') !== false) {
            return false;
        }
        // Paths starting with '/' are valid (e.g., /assets/file.js)
        return true;
    }
}
