<?php

namespace ApiDev\Exceptions;

/**
 * Exception for invalid HTTP requests.
 * 
 * Thrown when a request is malformed or contains invalid data.
 * Returns HTTP status code 400 (Bad Request).
 */
class InvalidRequestException extends RequestException
{
    /**
     * Returns the HTTP status code for invalid requests.
     * 
     * @return int HTTP status code 400
     */
    public function getHttpStatusCode(): int
    {
        return 400;
    }
}
