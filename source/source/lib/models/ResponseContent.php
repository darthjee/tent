<?php

namespace Tent\Models;

interface ResponseContent
{
    /**
     * Returns the content of the response.
     *
     * @return string
     */
    public function content(): string;

    /**
     * Returns the headers associated with the response content.
     *
     * @return array
     */
    public function headers(): array;

    /**
     * Returns the HTTP status code for the response content.
     *
     * @return integer
     */
    public function httpCode(): int;

    /**
     * Checks if the content exists.
     *
     * @return boolean
     */
    public function exists(): bool;
}
