<?php

namespace ApiDev\Exceptions;

/**
 * Exception for requests that are syntactically valid but semantically incorrect.
 *
 * Thrown when a request cannot be processed due to invalid content
 * (e.g. unsupported file type).
 * Returns HTTP status code 422 (Unprocessable Entity).
 */
class UnprocessableEntityException extends RequestException
{
    /**
     * Returns the HTTP status code for unprocessable entity errors.
     *
     * @return int HTTP status code 422
     */
    public function getHttpStatusCode(): int
    {
        return 422;
    }
}
