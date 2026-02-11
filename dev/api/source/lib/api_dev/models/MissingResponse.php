<?php

namespace ApiDev;

/**
 * Represents a 404 Not Found HTTP response.
 *
 * A specialized Response class for handling missing resources or routes.
 * Returns a 404 status code with a "Not Found" message.
 */
class MissingResponse extends Response
{
    /**
     * Creates a new MissingResponse instance.
     *
     * Initializes the response with a "Not Found" body, 404 status code,
     * and plain text content type header.
     */
    public function __construct()
    {
        parent::__construct("Not Found", 404, ['Content-Type: text/plain']);
    }
}
