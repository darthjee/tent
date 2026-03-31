<?php

namespace ApiDev\Exceptions;

/**
 * Exception for resources that cannot be found.
 *
 * Thrown when a requested resource does not exist.
 * Returns HTTP status code 404 (Not Found).
 */
class NotFoundException extends RequestException
{
    /**
     * Returns the HTTP status code for not found errors.
     *
     * @return int HTTP status code 404
     */
    public function getHttpStatusCode(): int
    {
        return 404;
    }
}
