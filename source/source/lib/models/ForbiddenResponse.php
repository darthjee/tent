<?php

namespace Tent\Models;

use Tent\Models\Response;
use Tent\Models\RequestInterface;

/**
 * Response representing a 403 Forbidden error.
 *
 * This class is used when a request is denied due to forbidden access, such as path traversal attempts.
 * It always returns a 403 status code with a default body of "Forbidden".
 * In the future, it may support configuration to allow custom content for forbidden responses.
 */
class ForbiddenResponse extends Response
{
    /**
     * Constructs a ForbiddenResponse with a 403 status and default body.
     */
    public function __construct(RequestInterface $request)
    {
        parent::__construct([
            'body' => "Forbidden", 'httpCode' => 403,
            'headers' => ['Content-Type: text/plain'],
            'request' => $request
        ]);
    }
}
