<?php

namespace ApiDev\Exceptions;

/**
 * Base class for request-related exceptions.
 * 
 * This abstract exception class represents HTTP request errors and requires
 * subclasses to define the appropriate HTTP status code for the error.
 */
abstract class RequestException extends \Exception
{
    /**
     * Returns the HTTP status code for this exception.
     * 
     * @return int The HTTP status code (e.g., 400, 404, 500)
     */
    abstract public function getHttpStatusCode(): int;
}
