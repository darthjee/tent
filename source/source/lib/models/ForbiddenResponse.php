<?php

namespace Tent;

use Tent\Response;

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
    public function __construct()
    {
        parent::__construct("Forbidden", 403, ['Content-Type: text/plain']);
    }
}
