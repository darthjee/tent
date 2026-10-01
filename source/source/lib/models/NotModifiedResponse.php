<?php

namespace Tent\Models;

/**
 * Response representing a 304 Not Modified answer to a conditional request.
 *
 * It always has an empty body and carries only the cache validator headers
 * (ETag / Last-Modified), never Content-Type / Content-Length.
 */
class NotModifiedResponse extends Response
{
    /**
     * Constructs a NotModifiedResponse with a 304 status and an empty body.
     *
     * @param RequestInterface $request The original request associated with this response.
     * @param array            $headers The validator header lines (e.g. ETag, Last-Modified).
     */
    public function __construct(RequestInterface $request, array $headers)
    {
        parent::__construct([
            'body' => '',
            'httpCode' => 304,
            'headers' => $headers,
            'request' => $request
        ]);
    }
}
