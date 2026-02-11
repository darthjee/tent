<?php

namespace ApiDev\Exceptions;

/**
 * Exception for server-side errors.
 * 
 * Thrown when an internal server error occurs during request processing.
 * Returns HTTP status code 500 (Internal Server Error).
 */
class ServerErrorException extends RequestException
{
    /**
     * Returns the HTTP status code for server errors.
     * 
     * @return int HTTP status code 500
     */
    public function getHttpStatusCode(): int
    {
        return 500;
    }
}
