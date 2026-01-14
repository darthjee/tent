<?php

namespace Tent;

use Tent\Response;

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
    public function __construct()
    {
        parent::__construct("Not Found", 404, ['Content-Type: text/plain']);
    }
}
