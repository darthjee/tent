<?php

namespace Tent\Models;

use Tent\Models\Response;

/**
 * Response representing a 404 Not Found error.
 *
 * This class is used when no handler matches the request. It always returns a 404 status code
 * with a default body of "Not Found". In the future, it may support configuration via
 * MissingResponse::setBodyFile($filePath) to allow custom HTML or other content for missing pages.
 */
class MissingResponse extends Response
{
    /**
     * Constructs a MissingResponse with a 404 status and default body.
     */
    public function __construct(RequestInterface $request)
    {
        parent::__construct([
            'body' => 'Not Found',
            'httpCode' => 404,
            'headers' => ['Content-Type: text/plain'],
            'request' => $request
        ]);
    }
}
