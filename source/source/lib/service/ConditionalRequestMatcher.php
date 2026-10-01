<?php

namespace Tent\Service;

use Tent\Models\RequestInterface;

/**
 * Evaluates the conditional headers of a request (If-None-Match / If-Modified-Since)
 * against the current validators of a resource.
 *
 * - If-None-Match takes precedence: when present, If-Modified-Since is ignored.
 * - If-None-Match supports "*", comma-separated lists and weak (W/) comparison.
 * - If-Modified-Since matches when the given date is at or after the last modification time.
 *   Unparseable dates are ignored.
 */
class ConditionalRequestMatcher
{
    /**
     * @var RequestInterface The request carrying the conditional headers.
     */
    private RequestInterface $request;

    /**
     * @var string The current (quoted) ETag of the resource.
     */
    private string $etag;

    /**
     * @var integer The last modification time of the resource (Unix timestamp).
     */
    private int $lastModified;

    /**
     * @var array|null Lower-cased request headers, lazily built.
     */
    private ?array $headers = null;

    /**
     * Constructs a ConditionalRequestMatcher.
     *
     * @param RequestInterface $request      The request carrying the conditional headers.
     * @param string           $etag         The current (quoted) ETag of the resource.
     * @param integer          $lastModified The last modification time (Unix timestamp).
     */
    public function __construct(RequestInterface $request, string $etag, int $lastModified)
    {
        $this->request = $request;
        $this->etag = $etag;
        $this->lastModified = $lastModified;
    }

    /**
     * Checks whether the resource is unchanged according to the request's conditional headers.
     *
     * @return boolean True when a 304 Not Modified should be returned.
     */
    public function isNotModified(): bool
    {
        $ifNoneMatch = $this->header('if-none-match');
        if ($ifNoneMatch !== null) {
            return $this->etagMatches($ifNoneMatch);
        }

        $ifModifiedSince = $this->header('if-modified-since');
        if ($ifModifiedSince !== null) {
            return $this->notModifiedSince($ifModifiedSince);
        }

        return false;
    }

    /**
     * Checks whether an If-None-Match value matches the current ETag (weak comparison).
     *
     * @param string $value The If-None-Match header value.
     * @return boolean
     */
    private function etagMatches(string $value): bool
    {
        if (trim($value) === '*') {
            return true;
        }

        $current = $this->normalizeEtag($this->etag);
        foreach (explode(',', $value) as $candidate) {
            if ($this->normalizeEtag($candidate) === $current) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks whether an If-Modified-Since value is at or after the last modification time.
     *
     * @param string $value The If-Modified-Since header value.
     * @return boolean
     */
    private function notModifiedSince(string $value): bool
    {
        $since = strtotime($value);
        if ($since === false) {
            return false;
        }

        return $since >= $this->lastModified;
    }

    /**
     * Normalizes an ETag for weak comparison (trims and strips a leading W/).
     *
     * @param string $etag The ETag to normalize.
     * @return string
     */
    private function normalizeEtag(string $etag): string
    {
        $etag = trim($etag);
        if (strncmp($etag, 'W/', 2) === 0) {
            $etag = substr($etag, 2);
        }

        return $etag;
    }

    /**
     * Returns a request header value, looked up case-insensitively.
     *
     * @param string $name The lower-cased header name.
     * @return string|null The header value, or null when absent.
     */
    private function header(string $name): ?string
    {
        if ($this->headers === null) {
            $this->headers = array_change_key_case((array) $this->request->headers(), CASE_LOWER);
        }

        $value = $this->headers[$name] ?? null;

        return $value === null ? null : (string) $value;
    }
}
